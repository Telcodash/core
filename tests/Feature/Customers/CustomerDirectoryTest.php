<?php

namespace Tests\Feature\Customers;

use App\Services\Billing\BillingDirectory;
use App\Services\Customers\CustomerDirectory;
use App\Services\TaskQueue\QueueServiceException;
use App\Services\TaskQueue\TaskFailedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerDirectoryTest extends TestCase
{
    use FakesDaemons, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.queue.url' => 'http://queue.test', 'services.queue.token' => 'test-token']);
    }

    private function customers(): CustomerDirectory
    {
        return $this->app->make(CustomerDirectory::class);
    }

    private function billing(): BillingDirectory
    {
        return $this->app->make(BillingDirectory::class);
    }

    public function test_search_sends_an_ephemeral_waiting_task_and_maps_the_page(): void
    {
        $this->fakeDaemons(['customers.search' => ['data' => [self::customerFixture()], 'total' => 41, 'page' => 2, 'per_page' => 20]]);

        $page = $this->customers()->search('ada lov', ['postcode' => 'M14'], page: 2);

        $this->assertSame(41, $page->total());
        $customer = $page->items()[0];
        $this->assertSame('Dr Ada Lovelace', $customer->fullName());
        $this->assertSame('1978-05-03', $customer->dateOfBirth->toDateString());
        $this->assertSame(['Flat 4', '12 Station Road', 'MANCHESTER', 'Greater Manchester', 'M14 5RG'], $customer->address->lines());

        Http::assertSent(fn (Request $request) => $request->url() === 'http://queue.test/tasks?wait=5000'
            && json_decode($request->body(), true) === [
                'queue' => 'customers',
                'name' => 'customers.search',
                'payload' => ['query' => 'ada lov', 'postcode' => 'M14', 'page' => 2, 'per_page' => 20],
                'options' => ['ephemeral' => true, 'attempts' => 1],
            ]);
    }

    public function test_lookups_are_never_persisted_in_core(): void
    {
        $this->fakeDaemons(['customers.get' => self::customerFixture()]);

        $this->customers()->find('cus_01A24B7MY1G5VD193QWE5EZXAV');

        $this->assertDatabaseCount('queue_tasks', 0);
    }

    public function test_find_returns_null_for_unknown_customers_and_find_many_maps_lists(): void
    {
        $this->fakeDaemons([
            'customers.get' => null,
            'customers.get_many' => [self::customerFixture('cus_01ARZ3NDEKTSV4RRFFQ69G5FAV'), self::customerFixture()],
        ]);

        $this->assertNull($this->customers()->find('cus_01ARZ3NDEKTSV4RRFFQ69G5FAV'));
        $this->assertSame(
            ['cus_01ARZ3NDEKTSV4RRFFQ69G5FAV', 'cus_01A24B7MY1G5VD193QWE5EZXAV'],
            array_map(fn ($c) => $c->id, $this->customers()->findMany(['cus_01ARZ3NDEKTSV4RRFFQ69G5FAV', 'cus_01A24B7MY1G5VD193QWE5EZXAV'])),
        );
        $this->assertSame([], $this->customers()->findMany([]));
    }

    public function test_billing_account_lookups_go_to_the_billing_queue(): void
    {
        $this->fakeDaemons(['accounts.get' => self::accountFixture()]);

        $account = $this->billing()->findAccount('4883209140');
        $this->billing()->findAccount('acc_01A7DM1QT4J21DF5TF9A6EH132');

        $this->assertSame('cus_01A24B7MY1G5VD193QWE5EZXAV', $account?->customerId);
        $this->assertSame(2900, $account->overduePence);
        $this->assertTrue($account->paymentMethod->expired);
        Http::assertSent(fn (Request $r) => $r['queue'] === 'billing' && json_decode($r->body(), true)['payload'] === ['account_number' => '4883209140']);
        Http::assertSent(fn (Request $r) => json_decode($r->body(), true)['payload'] === ['id' => 'acc_01A7DM1QT4J21DF5TF9A6EH132']);
    }

    public function test_invoices_map_line_items_and_payment_attempts(): void
    {
        $this->fakeDaemons(['invoices.list' => ['data' => [self::invoiceFixture()], 'total' => 9, 'page' => 1, 'per_page' => 6]]);

        $page = $this->billing()->invoices('acc_01A7DM1QT4J21DF5TF9A6EH132', perPage: 6);
        $invoice = $page->items()[0];

        $this->assertSame(9, $page->total());
        $this->assertSame('INV-00000042', $invoice->invoiceNumber);
        $this->assertSame(['plan', 'extra', null], array_column($invoice->lineItems, 'kind'));
        $this->assertSame('447700910123', $invoice->lineItems[1]['msisdn']);
        $this->assertNull($invoice->lineItems[2]['msisdn']); // invoices from before per-line billing
        $this->assertSame('expired_card', $invoice->payments[0]->failureCode);
    }

    public function test_daemon_rejections_raise_task_failed(): void
    {
        Http::fake(['queue.test/tasks*' => Http::response(['state' => 'failed', 'result' => null, 'error' => 'Invalid payload: ✖ Provide at least one search criterion'])]);

        $this->expectException(TaskFailedException::class);
        $this->expectExceptionMessage('Provide at least one search criterion');

        $this->customers()->search();
    }

    public function test_daemon_down_raises_queue_service_exception(): void
    {
        $this->fakeDaemons(['customers.get' => self::down()]);

        try {
            $this->customers()->find('cus_01A24B7MY1G5VD193QWE5EZXAV');
            $this->fail('Expected exception');
        } catch (QueueServiceException $e) {
            $this->assertTrue($e->isTransient());
            $this->assertStringContainsString('No workers', $e->getMessage());
        }
    }
}
