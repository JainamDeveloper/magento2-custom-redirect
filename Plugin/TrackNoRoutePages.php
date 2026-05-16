<?php

declare(strict_types=1);

namespace Magneto\CustomRedirect\Plugin;

use Psr\Log\LoggerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Cms\Controller\Noroute\Index;

class TrackNoRoutePages
{
    /**
     * @param LoggerInterface $logger
     * @param UrlInterface $urlInterface
     * @param UrlFinderInterface $urlFinderInterface
     * @param ProductRepositoryInterface $productRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param RedirectFactory $resultRedirectFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UrlInterface $urlInterface,
        private readonly UrlFinderInterface $urlFinderInterface,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Intercept 404 and issue a 301 redirect to the most relevant active page.
     *
     * @param Index $subject
     * @param callable $proceed
     * @return ResultInterface
     */
    public function aroundExecute(Index $subject, callable $proceed): ResultInterface
    {
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setHttpResponseCode(301);

        $store       = $this->storeManager->getStore();
        $currentUrl  = $this->urlInterface->getCurrentUrl();
        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- parse_url is safe here; result cast to string
        $parsedPath  = (string) parse_url($currentUrl, PHP_URL_PATH);
        $requestPath = ltrim($parsedPath, '/');

        $rewrite = $this->urlFinderInterface->findOneByData([
            'request_path' => $requestPath,
            'store_id'     => $store->getId(),
        ]);

        if ($rewrite) {
            return $this->resolveFromRewrite(
                $rewrite->getTargetPath(),
                $redirect,
                $store->getBaseUrl(),
                (int) $store->getId()
            );
        }

        // Legacy external URL pattern: view-shape/<sku>
        if (preg_match('/^view-shape\/(.+)$/', $requestPath, $matches)) {
            try {
                $product = $this->productRepository->get($matches[1]);
                return $redirect->setPath($product->getProductUrl());
            } catch (\Exception $e) {
                $this->logger->error('CustomRedirect view-shape error: ' . $e->getMessage());
            }
        }

        return $redirect->setPath($store->getBaseUrl());
    }

    /**
     * Dispatch rewrite target path to the correct redirect resolver.
     *
     * @param string $targetPath
     * @param Redirect $redirect
     * @param string $baseUrl
     * @param int $storeId
     * @return ResultInterface
     */
    private function resolveFromRewrite(
        string $targetPath,
        Redirect $redirect,
        string $baseUrl,
        int $storeId
    ): ResultInterface {
        $matches = [];

        if (strpos($targetPath, 'catalog/category/view') !== false) {
            preg_match('/id\/(\d+)/', $targetPath, $matches);
            return $this->redirectToActiveParentCategory((int)($matches[1] ?? 0), $redirect, $baseUrl, $storeId);
        }

        if (strpos($targetPath, 'catalog/product/view') !== false) {
            preg_match('/id\/(\d+)/', $targetPath, $matches);
            return $this->redirectToProductCategory((int)($matches[1] ?? 0), $redirect, $baseUrl, $storeId);
        }

        return $redirect->setPath($baseUrl);
    }

    /**
     * Redirect to nearest active ancestor — reversed so closest parent is checked first.
     *
     * @param int $categoryId
     * @param Redirect $redirect
     * @param string $baseUrl
     * @param int $storeId
     * @return ResultInterface
     */
    private function redirectToActiveParentCategory(
        int $categoryId,
        Redirect $redirect,
        string $baseUrl,
        int $storeId
    ): ResultInterface {
        if (!$categoryId) {
            return $redirect->setPath($baseUrl);
        }

        try {
            $category = $this->categoryRepository->get($categoryId, $storeId);

            foreach (array_reverse($category->getParentIds()) as $parentId) {
                $parent = $this->categoryRepository->get($parentId, $storeId);
                if ($parent->getIsActive() && $parent->getLevel() > 1) {
                    return $redirect->setPath($parent->getUrl());
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('CustomRedirect category error: ' . $e->getMessage());
        }

        return $redirect->setPath($baseUrl);
    }

    /**
     * Redirect to the first active category the product belongs to.
     *
     * @param int $productId
     * @param Redirect $redirect
     * @param string $baseUrl
     * @param int $storeId
     * @return ResultInterface
     */
    private function redirectToProductCategory(
        int $productId,
        Redirect $redirect,
        string $baseUrl,
        int $storeId
    ): ResultInterface {
        if (!$productId) {
            return $redirect->setPath($baseUrl);
        }

        try {
            $product     = $this->productRepository->getById($productId, false, $storeId);
            $categoryIds = $product->getCategoryIds();
            $parentIds   = [];

            foreach ($categoryIds as $categoryId) {
                $category = $this->categoryRepository->get($categoryId, $storeId);
                if ($category->getIsActive() && $category->getLevel() > 1) {
                    return $redirect->setPath($category->getUrl());
                }
                $parentIds[] = array_reverse($category->getParentIds());
            }

            foreach (array_unique(array_merge(...$parentIds)) as $categoryId) {
                $category = $this->categoryRepository->get($categoryId, $storeId);
                if ($category->getIsActive() && $category->getLevel() > 1) {
                    return $redirect->setPath($category->getUrl());
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('CustomRedirect product error: ' . $e->getMessage());
        }

        return $redirect->setPath($baseUrl);
    }
}
