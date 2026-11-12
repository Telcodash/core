{{-- Decorative network: nodes and links with signal travelling along a few routes. --}}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 370" fill="none" aria-hidden="true" {{ $attributes }}>
    <g stroke="currentColor" stroke-opacity="0.14" stroke-width="1.25">
        <line x1="60" y1="80" x2="170" y2="48" />
        <line x1="170" y1="48" x2="290" y2="112" />
        <line x1="290" y1="112" x2="414" y2="66" />
        <line x1="60" y1="80" x2="118" y2="192" />
        <line x1="170" y1="48" x2="118" y2="192" />
        <line x1="290" y1="112" x2="240" y2="222" />
        <line x1="118" y1="192" x2="240" y2="222" />
        <line x1="240" y1="222" x2="372" y2="232" />
        <line x1="290" y1="112" x2="372" y2="232" />
        <line x1="414" y1="66" x2="372" y2="232" />
        <line x1="118" y1="192" x2="70" y2="306" />
        <line x1="240" y1="222" x2="200" y2="322" />
        <line x1="70" y1="306" x2="200" y2="322" />
        <line x1="200" y1="322" x2="330" y2="324" />
        <line x1="372" y1="232" x2="330" y2="324" />
        <line x1="372" y1="232" x2="446" y2="298" />
        <line x1="330" y1="324" x2="446" y2="298" />
        <line x1="170" y1="48" x2="240" y2="222" />
    </g>
    <g stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path class="network-flow" d="M60 80 L170 48 L290 112 L372 232 L446 298" stroke="oklch(0.78 0.15 275)" />
        <path class="network-flow" style="animation-delay:-1.4s" d="M70 306 L118 192 L240 222 L372 232 L330 324" stroke="oklch(0.78 0.15 275)" />
        <path class="network-flow" style="animation-delay:-2.2s" d="M414 66 L290 112 L240 222 L200 322" stroke="oklch(0.82 0.15 72)" />
    </g>
    <circle cx="60" cy="80" r="3.5" fill="currentColor" fill-opacity="0.35" />
    <circle cx="170" cy="48" r="3.5" fill="currentColor" fill-opacity="0.35" />
    <circle cx="414" cy="66" r="3.5" fill="currentColor" fill-opacity="0.35" />
    <circle cx="118" cy="192" r="3.5" fill="currentColor" fill-opacity="0.35" />
    <circle cx="70" cy="306" r="3.5" fill="currentColor" fill-opacity="0.35" />
    <circle cx="200" cy="322" r="3.5" fill="currentColor" fill-opacity="0.35" />
    <circle cx="330" cy="324" r="3.5" fill="currentColor" fill-opacity="0.35" />
    <circle cx="446" cy="298" r="3.5" fill="currentColor" fill-opacity="0.35" />
    <circle class="network-ping" cx="240" cy="222" r="7" fill="oklch(0.78 0.15 275)" />
    <circle class="network-ping" style="animation-delay:-1.4s" cx="414" cy="66" r="5" fill="oklch(0.82 0.15 72)" />
    <circle cx="290" cy="112" r="6" fill="oklch(0.215 0.06 275)" stroke="oklch(0.78 0.15 275)" stroke-width="2" />
    <circle cx="372" cy="232" r="6" fill="oklch(0.215 0.06 275)" stroke="oklch(0.78 0.15 275)" stroke-width="2" />
    <circle cx="240" cy="222" r="7" fill="oklch(0.78 0.15 275)" />
    <circle cx="414" cy="66" r="5" fill="oklch(0.82 0.15 72)" />
</svg>
