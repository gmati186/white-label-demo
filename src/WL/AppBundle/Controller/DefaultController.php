<?php

namespace WL\AppBundle\Controller;

use Nokaut\ApiKit\ClientApi\Rest\Fetch\ProductsFetch;
use Nokaut\ApiKit\ClientApi\Rest\Query\ProductsQuery;
use Nokaut\ApiKit\Collection\Products;
use Nokaut\ApiKit\Repository\CategoriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use WL\AppBundle\Lib\CategoriesAllowed;
use WL\AppBundle\Lib\Filter;
use WL\AppBundle\Lib\Repository\ProductsRepository;
use WL\AppBundle\Lib\RepositoryFactory;
use WL\AppBundle\Lib\Type\Breadcrumb;
use WL\AppBundle\Lib\Repository\ProductsAsyncRepository;

class DefaultController extends Controller
{
    public function indexAction()
    {
        $breadcrumbs = array();
        $breadcrumbs[] = new Breadcrumb("Strona główna");

        /** @var RepositoryFactory $productsAsyncRepo */
        $repositoryFactory = $this->get('repo.factory.cache.file');
        $productsRepo = $repositoryFactory->getProductsAsyncRepository();

        /** @var CategoriesAllowed $categoriesAllowed */
        $categoriesAllowed = $this->get('categories.allowed');

        if ($categoriesAllowed->isAllowedAllCategories()) {
            $productsFetchGroupCategory = $this->fetchProductsFromAllCategories($repositoryFactory);
        } else {
            $productsFetchGroupCategory = $this->fetchProductsFormAllowedCategories($categoriesAllowed, $productsRepo);
        }

        $productsRepo->fetchAllAsync();

        $productsGroupCategory = [];
        foreach ($productsFetchGroupCategory as $productsFetch) {
            /** @var ProductsFetch $productsFetch */
            $products = $productsFetch->getResult();
            $this->filter($products);
            $productsGroupCategory[] = $products;
        }


        return $this->render('WLAppBundle:Default:index.html.twig',
            [
                'breadcrumbs' => $breadcrumbs,
                'productsGroupCategory' => $productsGroupCategory
            ]);
    }

    /**
     * @param ProductsAsyncRepository $productsAsyncRepo
     * @param $categoriesIds
     * @return ProductsFetch
     */
    protected function fetchProducts(ProductsAsyncRepository $productsAsyncRepo, $categoriesIds)
    {
        $query = new ProductsQuery($this->container->getParameter('api_url'));
        $query->setCategoryIds($categoriesIds);
        $query->setFields(ProductsRepository::$fieldsForList);
        $query->setOrder('views', 'desc');
        $query->setLimit(18);
        return $productsAsyncRepo->fetchProductsWithBestOfferByQuery($query);
    }

    /**
     * @param Products $products
     */
    protected function filter($products)
    {
        if ($products) {
            $filterProperties = new Filter\PropertiesFilter();
            $filterProperties->filterProducts($products);

            $filterUrl = new Filter\Controller\UrlCategoryFilter();
            $filterUrl->filter($products);
        }
    }

    /**
     * @param $repositoryFactory
     * @return array
     */
    protected function fetchProductsFromAllCategories($repositoryFactory)
    {
        $productsFetchGroupCategory = [];
        /** @var CategoriesRepository $categoriesRepo */
        $categoriesRepo = $repositoryFactory->getCategoriesRepository();
        $productsRepo = $repositoryFactory->getProductsAsyncRepository();
        $categories = $categoriesRepo->fetchMenuCategories();

        foreach ($categories as $category) {
            if ($category->getId() == 9768) {//pominąć erotykę
                continue;
            }
            $productsFetchGroupCategory[] = $this->fetchProducts($productsRepo, [$category->getId()]);
        }
        return $productsFetchGroupCategory;
    }

    /**
     * @param $categoriesAllowed
     * @param $productsRepo
     * @param $productsFetchGroupCategory
     * @return array
     */
    protected function fetchProductsFormAllowedCategories($categoriesAllowed, $productsRepo)
    {
        $productsFetchGroupCategory = [];
        foreach ($categoriesAllowed->getParametersCategories() as $allowedGroupCategories) {
            $productsFetchGroupCategory[] = $this->fetchProducts($productsRepo, $allowedGroupCategories);
        }
        return $productsFetchGroupCategory;
    }
}
