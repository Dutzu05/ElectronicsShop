<?php
declare(strict_types=1);

function product_asset(string $productName, ?string $categoryName = null): array
{
    $assets = [
        'Lenovo IdeaPad 15' => ['image' => 'assets/products/lenovo-ideapad-15.svg'],
        'ASUS VivoBook' => ['image' => 'assets/products/asus-vivobook.svg'],
        'iPhone 13' => ['image' => 'assets/products/iphone-13.svg'],
        'Samsung Galaxy A55' => ['image' => 'assets/products/samsung-galaxy-a55.svg'],
        'Dell 24 inch Monitor' => ['image' => 'assets/products/dell-monitor.svg'],
        'LG UltraWide Monitor' => ['image' => 'assets/products/lg-ultrawide-monitor.svg'],
        'Logitech Mouse' => ['image' => 'assets/products/logitech-mouse.svg'],
        'Mechanical Keyboard' => ['image' => 'assets/products/mechanical-keyboard.svg'],
    ];

    if (isset($assets[$productName])) {
        return $assets[$productName];
    }

    $categoryFallbacks = [
        'Laptopuri' => 'assets/products/category-laptop.svg',
        'Telefoane' => 'assets/products/category-phone.svg',
        'Monitoare' => 'assets/products/category-monitor.svg',
        'Accesorii' => 'assets/products/category-accessory.svg',
    ];

    return [
        'image' => $categoryFallbacks[$categoryName ?? ''] ?? 'assets/products/category-generic.svg',
    ];
}
