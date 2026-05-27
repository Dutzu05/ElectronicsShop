<?php
declare(strict_types=1);

function product_asset(string $productName): array
{
    $assets = [
        'Lenovo IdeaPad 15' => [
            'image' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Lenovo%20Ideapad%203%20front%20view.png',
            'source' => 'https://www.lenovo.com/',
        ],
        'ASUS VivoBook' => [
            'image' => 'https://commons.wikimedia.org/wiki/Special:FilePath/ASUS%20Vivobook%2015%20X1504VA.png',
            'source' => 'https://www.asus.com/laptops/for-home/vivobook/',
        ],
        'iPhone 13' => [
            'image' => 'https://commons.wikimedia.org/wiki/Special:FilePath/IPhone%2013%20vector.svg',
            'source' => 'https://www.apple.com/iphone-13/',
        ],
        'Samsung Galaxy A55' => [
            'image' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Samsung%20Galaxy%20A55%205G.png',
            'source' => 'https://www.samsung.com/global/galaxy/galaxy-a55-5g/',
        ],
        'Dell 24 inch Monitor' => [
            'image' => 'https://commons.wikimedia.org/wiki/Special:FilePath/LCD%20Monitor.svg',
            'source' => 'https://www.dell.com/en-us/shop/monitors-monitor-accessories/ar/4009',
        ],
        'LG UltraWide Monitor' => [
            'image' => 'https://commons.wikimedia.org/wiki/Special:FilePath/LG%20ultrawide%20monitor%2034WL750.png',
            'source' => 'https://www.lg.com/global/business/monitors/lg-ultrawide-monitor',
        ],
        'Logitech Mouse' => [
            'image' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Logitech%20mouse%20M90.jpg',
            'source' => 'https://www.logitech.com/en-us/shop/c/mice',
        ],
        'Mechanical Keyboard' => [
            'image' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Mechanical%20keyboard%20-%20flickr%20-%20omegatron.jpg',
            'source' => 'https://commons.wikimedia.org/wiki/Category:Mechanical_keyboards',
        ],
    ];

    return $assets[$productName] ?? [
        'image' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Question%20mark%20in%20circle.svg',
        'source' => 'https://commons.wikimedia.org/',
    ];
}
