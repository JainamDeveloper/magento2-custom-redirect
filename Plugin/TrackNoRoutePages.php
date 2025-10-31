<?php

namespace Magneto\CustomRedirect\Plugin;

use Psr\Log\LoggerInterface;
use Magento\Framework\UrlInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Store\Model\StoreManagerInterface;

class TrackNoRoutePages
{
    protected $logger;

    protected $urlInterface;

    protected $urlFinderInterface;

    protected $productRepository;

    protected $categoryRepository;

    protected $resultRedirectFactory;

    protected $storeManager;

    public function __construct(
        LoggerInterface $logger,
        UrlInterface $urlInterface,
        UrlFinderInterface $urlFinderInterface,
        ProductRepositoryInterface $productRepository,
        CategoryRepositoryInterface $categoryRepository,
        RedirectFactory $resultRedirectFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->logger = $logger;
        $this->urlInterface = $urlInterface;
        $this->urlFinderInterface = $urlFinderInterface;
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->storeManager = $storeManager;
    }
    public function aroundExecute(
        \Magento\Cms\Controller\Noroute\Index $subject,
        callable $proceed
    ) {
        $this->logger->debug('NoRoute URL pluging call');
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setHttpResponseCode(301);
        $currentUrl = $this->urlInterface->getCurrentUrl();
        $parsedUrl = parse_url($currentUrl, PHP_URL_PATH);
        $requestPath = ltrim($parsedUrl, '/');
        $rewrite = $this->urlFinderInterface->findOneByData(['request_path' => $requestPath]);
        $store = $this->storeManager->getStore();
        if ($rewrite) {
            $targetPath = $rewrite->getTargetPath();
            $matches = array();
            $categoryId = '';
            $productId = '';
            if (strpos($targetPath, 'catalog/category/view') !== false) {
                preg_match('/id\/(\d+)/', $targetPath, $matches);
                $categoryId = $matches[1];

                if (!empty($categoryId)) {
                    try {

                        $category = $this->categoryRepository->get($categoryId);
                        foreach ($category->getParentIds() as $parentCategoryId) {
                            $parentCategory = $this->categoryRepository->get($parentCategoryId);
                            if ($parentCategory->getIsActive() && $parentCategory->getLevel() != 0 && $parentCategory->getLevel() != 1) {
                                $categoryUrl = $parentCategory->getUrl();
                                return $resultRedirect->setPath($categoryUrl);
                            }
                        }
                    } catch (\Exception $e) {
                        return $resultRedirect->setPath($store->getBaseUrl());
                    }
                } else {
                    return $resultRedirect->setPath($store->getBaseUrl());
                }
            } elseif (strpos($targetPath, 'catalog/product/view') !== false) {

                preg_match('/id\/(\d+)/', $targetPath, $matches);

                if (count($matches) > 0) {
                    $productId = $matches[1];
                }

                if (!empty($productId)) {
                    try {
                        $product = $this->productRepository->getById($productId);
                        if ($product) {
                            $cats = $product->getCategoryIds();
                            if ($cats) {
                                $catsFirst = array();
                                foreach ($cats as $categoryId) {
                                    $category = $this->categoryRepository->get($categoryId);
                                    if ($category->getIsActive() && $category->getLevel() != 0 && $category->getLevel() != 1) {
                                        return $resultRedirect->setPath($category->getUrl());
                                    } else {
                                        $catsFirst = array_merge($catsFirst, $category->getParentIds());
                                    }
                                }

                                if (count($catsFirst) > 0) {
                                    $catsFirst = array_unique($catsFirst);
                                    $url = "";
                                    foreach ($catsFirst as $categoryId) {
                                        $category = $this->categoryRepository->get($categoryId);
                                        if ($category->getLevel() != 0 && $category->getLevel() != 1) {
                                            if ($category->getIsActive()) {
                                                $url = $category->getUrl();
                                                break;
                                            }
                                        }
                                    }
                                    if (!empty($url)) {
                                        return $resultRedirect->setPath($url);
                                    } else {
                                        return $resultRedirect->setPath($store->getBaseUrl());
                                    }
                                }
                            }
                        } else {
                            return $resultRedirect->setPath($store->getBaseUrl());
                        }
                    } catch (\Exception $e) {
                        return $resultRedirect->setPath($store->getBaseUrl());
                    }
                } else {
                    return $resultRedirect->setPath($store->getBaseUrl());
                }
            } else {
                return $resultRedirect->setPath($store->getBaseUrl());
            }
        } else {
            // Matrix Url Rewrite Code            
            if (preg_match("/view-shape/", $requestPath)) {
                $prodSku = trim($requestPath, "/view-shape/");
                $product = $this->productRepository->get($prodSku);
                $url = $product->getProductUrl();
                return $resultRedirect->setPath($url);
            }
            return $resultRedirect->setPath($store->getBaseUrl());
        }
        return $resultRedirect->setPath($store->getBaseUrl());
    }
}
