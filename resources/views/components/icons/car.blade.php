@props(['size' => 21])

<svg
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    {{ $attributes }}
>
    <path
        d="M5 11 6.5 7.5A2.4 2.4 0 0 1 8.7 6h6.6a2.4 2.4 0 0 1 2.2 1.5L19 11M4 11h16a2 2 0 0 1 2 2v4h-2M4 17H2v-4a2 2 0 0 1 2-2m3 6h10M6 14h.01M18 14h.01"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
    />
    <circle cx="6" cy="18" r="2" fill="currentColor" />
    <circle cx="18" cy="18" r="2" fill="currentColor" />
</svg>
