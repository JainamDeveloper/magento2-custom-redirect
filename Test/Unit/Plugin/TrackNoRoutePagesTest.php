<?php

declare(strict_types=1);

namespace Magneto\CustomRedirect\Test\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Store;
use Magento\Cms\Controller\Noroute\Index;
use Magneto\CustomRedirect\Plugin\TrackNoRoutePages;

class TrackNoRoutePagesTest extends TestCase
{
    /** @var TrackNoRoutePages */
    private TrackNoRoutePages $plugin;

    /** @var MockObject|LoggerInterface */
    private MockObject|LoggerInterface $logger;

    /** @var MockObject|UrlInterface */
    private MockObject|UrlInterface $urlInterface;

    /** @var MockObject|UrlFinderInterface */
    private MockObject|UrlFinderInterface $urlFinder;

    /** @var MockObject|ProductRepositoryInterface */
    private MockObject|ProductRepositoryInterface $productRepository;

    /** @var MockObject|CategoryRepositoryInterface */
    private MockObject|CategoryRepositoryInterface $categoryRepository;

    /** @var MockObject|RedirectFactory */
    private MockObject|RedirectFactory $redirectFactory;

    /** @var MockObject|StoreManagerInterface */
    private MockObject|StoreManagerInterface $storeManager;

    /** @var MockObject|Redirect */
    private MockObject|Redirect $redirect;

    /** @var MockObject|Store */
    private MockObject|Store $store;

    /** @var MockObject|Index */
    private MockObject|Index $subject;

    protected function setUp(): void
    {
        $this->logger            = $this->createMock(LoggerInterface::class);
        $this->urlInterface      = $this->createMock(UrlInterface::class);
        $this->urlFinder         = $this->createMock(UrlFinderInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->redirectFactory   = $this->createMock(RedirectFactory::class);
        $this->storeManager      = $this->createMock(StoreManagerInterface::class);
        $this->redirect          = $this->createMock(Redirect::class);
        $this->store             = $this->createMock(Store::class);
        $this->subject           = $this->createMock(Index::class);

        $this->redirectFactory->method('create')->willReturn($this->redirect);
        $this->redirect->method('setHttpResponseCode')->willReturnSelf();
        $this->redirect->method('setPath')->willReturnSelf();
        $this->store->method('getBaseUrl')->willReturn('https://store.test/');
        $this->store->method('getId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($this->store);

        $this->plugin = new TrackNoRoutePages(
            $this->logger,
            $this->urlInterface,
            $this->urlFinder,
            $this->productRepository,
            $this->categoryRepository,
            $this->redirectFactory,
            $this->storeManager
        );
    }

    // -------------------------------------------------------------------------
    // No rewrite found
    // -------------------------------------------------------------------------

    public function testUnknownUrlRedirectsToHomepage(): void
    {
        $this->urlInterface->method('getCurrentUrl')->willReturn('https://store.test/unknown-page');
        $this->urlFinder->method('findOneByData')->willReturn(null);

        $this->redirect->expects($this->once())->method('setPath')->with('https://store.test/');

        $this->plugin->aroundExecute($this->subject, fn() => null);
    }

    // -------------------------------------------------------------------------
    // Legacy view-shape/<sku> pattern
    // -------------------------------------------------------------------------

    public function testLegacyViewShapeRedirectsToProductUrl(): void
    {
        $this->urlInterface->method('getCurrentUrl')->willReturn('https://store.test/view-shape/SKU-001');
        $this->urlFinder->method('findOneByData')->willReturn(null);

        $product = $this->createMock(Product::class);
        $product->method('getProductUrl')->willReturn('https://store.test/product-name.html');
        $this->productRepository->method('get')->with('SKU-001')->willReturn($product);

        $this->redirect->expects($this->once())->method('setPath')->with('https://store.test/product-name.html');

        $this->plugin->aroundExecute($this->subject, fn() => null);
    }

    public function testLegacyViewShapeWithInvalidSkuFallsBackToHomepage(): void
    {
        $this->urlInterface->method('getCurrentUrl')->willReturn('https://store.test/view-shape/BAD-SKU');
        $this->urlFinder->method('findOneByData')->willReturn(null);

        $this->productRepository->method('get')->willThrowException(new \Exception('Not found'));
        $this->logger->expects($this->once())->method('error');

        $this->redirect->expects($this->once())->method('setPath')->with('https://store.test/');

        $this->plugin->aroundExecute($this->subject, fn() => null);
    }

    // -------------------------------------------------------------------------
    // Rewrite found — category target
    // -------------------------------------------------------------------------

    public function testDisabledCategoryRedirectsToNearestActiveParent(): void
    {
        $this->urlInterface->method('getCurrentUrl')->willReturn('https://store.test/smartphones.html');
        $rewrite = $this->mockRewrite('catalog/category/view/id/4');
        $this->urlFinder->method('findOneByData')->willReturn($rewrite);

        // Disabled category (id=4), parents: [0=Root, 1=Default, 2=Electronics, 3=Phones]
        $disabledCategory = $this->mockCategory(4, false, 3, [0, 1, 2, 3]);
        $root             = $this->mockCategory(0, true, 0);
        $default          = $this->mockCategory(1, true, 1);
        $electronics      = $this->mockCategory(2, true, 2, [], 'https://store.test/electronics.html');
        $phones           = $this->mockCategory(3, true, 3, [], 'https://store.test/phones.html');

        $this->categoryRepository->method('get')->willReturnMap([
            [4, 1, $disabledCategory],
            [0, 1, $root],
            [1, 1, $default],
            [2, 1, $electronics],
            [3, 1, $phones],
        ]);

        // array_reverse makes [3,2,1,0] — Phones (id=3) found first
        $this->redirect->expects($this->once())->method('setPath')->with('https://store.test/phones.html');

        $this->plugin->aroundExecute($this->subject, fn() => null);
    }

    public function testCategoryWithNoActiveParentFallsBackToHomepage(): void
    {
        $this->urlInterface->method('getCurrentUrl')->willReturn('https://store.test/gone.html');
        $rewrite = $this->mockRewrite('catalog/category/view/id/5');
        $this->urlFinder->method('findOneByData')->willReturn($rewrite);

        $category = $this->mockCategory(5, false, 3, [0, 1]);
        $root     = $this->mockCategory(0, true, 0);
        $default  = $this->mockCategory(1, true, 1);

        $this->categoryRepository->method('get')->willReturnMap([
            [5, 1, $category],
            [0, 1, $root],
            [1, 1, $default],
        ]);

        $this->redirect->expects($this->once())->method('setPath')->with('https://store.test/');

        $this->plugin->aroundExecute($this->subject, fn() => null);
    }

    public function testCategoryRepositoryExceptionFallsBackToHomepage(): void
    {
        $this->urlInterface->method('getCurrentUrl')->willReturn('https://store.test/broken.html');
        $rewrite = $this->mockRewrite('catalog/category/view/id/99');
        $this->urlFinder->method('findOneByData')->willReturn($rewrite);

        $this->categoryRepository->method('get')->willThrowException(new \Exception('DB error'));
        $this->logger->expects($this->once())->method('error');

        $this->redirect->expects($this->once())->method('setPath')->with('https://store.test/');

        $this->plugin->aroundExecute($this->subject, fn() => null);
    }

    // -------------------------------------------------------------------------
    // Rewrite found — product target
    // -------------------------------------------------------------------------

    public function testRemovedProductRedirectsToActiveCategory(): void
    {
        $this->urlInterface->method('getCurrentUrl')->willReturn('https://store.test/old-product.html');
        $rewrite = $this->mockRewrite('catalog/product/view/id/10');
        $this->urlFinder->method('findOneByData')->willReturn($rewrite);

        $product  = $this->createMock(Product::class);
        $product->method('getCategoryIds')->willReturn([3]);
        $this->productRepository->method('getById')->with(10, false, 1)->willReturn($product);

        $category = $this->mockCategory(3, true, 2, [], 'https://store.test/phones.html');
        $this->categoryRepository->method('get')->with(3, 1)->willReturn($category);

        $this->redirect->expects($this->once())->method('setPath')->with('https://store.test/phones.html');

        $this->plugin->aroundExecute($this->subject, fn() => null);
    }

    public function testRemovedProductWithInactiveCategoryFallsBackToParent(): void
    {
        $this->urlInterface->method('getCurrentUrl')->willReturn('https://store.test/old-product.html');
        $rewrite = $this->mockRewrite('catalog/product/view/id/10');
        $this->urlFinder->method('findOneByData')->willReturn($rewrite);

        $product = $this->createMock(Product::class);
        $product->method('getCategoryIds')->willReturn([5]);
        $this->productRepository->method('getById')->with(10, false, 1)->willReturn($product);

        // Direct category inactive — parent Electronics is active
        $inactiveCategory = $this->mockCategory(5, false, 3, [0, 1, 2]);
        $root             = $this->mockCategory(0, true, 0);
        $default          = $this->mockCategory(1, true, 1);
        $electronics      = $this->mockCategory(2, true, 2, [], 'https://store.test/electronics.html');

        $this->categoryRepository->method('get')->willReturnMap([
            [5, 1, $inactiveCategory],
            [0, 1, $root],
            [1, 1, $default],
            [2, 1, $electronics],
        ]);

        $this->redirect->expects($this->once())->method('setPath')->with('https://store.test/electronics.html');

        $this->plugin->aroundExecute($this->subject, fn() => null);
    }

    public function testProductWithInactiveCategoryRedirectsToNearestActiveParent(): void
    {
        $this->urlInterface->method('getCurrentUrl')->willReturn('https://store.test/old-product.html');
        $rewrite = $this->mockRewrite('catalog/product/view/id/10');
        $this->urlFinder->method('findOneByData')->willReturn($rewrite);

        $product = $this->createMock(Product::class);
        $product->method('getCategoryIds')->willReturn([5]);
        $this->productRepository->method('getById')->with(10, false, 1)->willReturn($product);

        // Direct category inactive, parents: [0=Root, 1=Default, 2=Electronics, 3=Phones]
        // array_reverse → [3=Phones, 2=Electronics, 1, 0] — Phones checked first
        $inactiveCategory = $this->mockCategory(5, false, 4, [0, 1, 2, 3]);
        $root             = $this->mockCategory(0, true, 0);
        $default          = $this->mockCategory(1, true, 1);
        $electronics      = $this->mockCategory(2, true, 2, [], 'https://store.test/electronics.html');
        $phones           = $this->mockCategory(3, true, 3, [], 'https://store.test/phones.html');

        $this->categoryRepository->method('get')->willReturnMap([
            [5, 1, $inactiveCategory],
            [0, 1, $root],
            [1, 1, $default],
            [2, 1, $electronics],
            [3, 1, $phones],
        ]);

        // Phones (id=3) is nearest active parent — must redirect there, not Electronics
        $this->redirect->expects($this->once())->method('setPath')->with('https://store.test/phones.html');

        $this->plugin->aroundExecute($this->subject, fn() => null);
    }

    public function testProductRepositoryExceptionFallsBackToHomepage(): void
    {
        $this->urlInterface->method('getCurrentUrl')->willReturn('https://store.test/old-product.html');
        $rewrite = $this->mockRewrite('catalog/product/view/id/99');
        $this->urlFinder->method('findOneByData')->willReturn($rewrite);

        $this->productRepository->method('getById')->willThrowException(new \Exception('Not found'));
        $this->logger->expects($this->once())->method('error');

        $this->redirect->expects($this->once())->method('setPath')->with('https://store.test/');

        $this->plugin->aroundExecute($this->subject, fn() => null);
    }

    // -------------------------------------------------------------------------
    // Rewrite found — unknown target
    // -------------------------------------------------------------------------

    public function testUnknownRewriteTargetFallsBackToHomepage(): void
    {
        $this->urlInterface->method('getCurrentUrl')->willReturn('https://store.test/some-page.html');
        $rewrite = $this->mockRewrite('cms/page/view/id/1');
        $this->urlFinder->method('findOneByData')->willReturn($rewrite);

        $this->redirect->expects($this->once())->method('setPath')->with('https://store.test/');

        $this->plugin->aroundExecute($this->subject, fn() => null);
    }

    // -------------------------------------------------------------------------
    // Multi-store: store_id passed to url finder
    // -------------------------------------------------------------------------

    public function testUrlFinderReceivesStoreId(): void
    {
        $this->store->method('getId')->willReturn(2);
        $this->urlInterface->method('getCurrentUrl')->willReturn('https://store2.test/page.html');

        $this->urlFinder->expects($this->once())
            ->method('findOneByData')
            ->with($this->arrayHasKey('store_id'))
            ->willReturn(null);

        $this->plugin->aroundExecute($this->subject, fn() => null);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function mockRewrite(string $targetPath): MockObject
    {
        $rewrite = $this->createMock(UrlRewrite::class);
        $rewrite->method('getTargetPath')->willReturn($targetPath);
        return $rewrite;
    }

    private function mockCategory(
        int $id,
        bool $isActive,
        int $level,
        array $parentIds = [],
        string $url = ''
    ): MockObject {
        $category = $this->createMock(Category::class);
        $category->method('getIsActive')->willReturn($isActive);
        $category->method('getLevel')->willReturn($level);
        $category->method('getParentIds')->willReturn($parentIds);
        $category->method('getUrl')->willReturn($url);
        return $category;
    }
}
