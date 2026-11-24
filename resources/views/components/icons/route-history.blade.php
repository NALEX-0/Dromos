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
        d="M7.5 18c5.5 0 2-11 8-11"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-dasharray="2.5 3"
    />
    <circle cx="6" cy="18" r="2.5" stroke="currentColor" stroke-width="2" />
    <path
        d="M19 7c0 2.4-3 5.5-3 5.5S13 9.4 13 7a3 3 0 1 1 6 0Z"
        stroke="currentColor"
        stroke-width="2"
        stroke-linejoin="round"
    />
    <circle cx="16" cy="7" r=".8" fill="currentColor" />
</svg>
