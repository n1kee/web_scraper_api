<?php

namespace App\Controller;

use FilesBundle\Helper\FileSystem;
use ImagesBundle\ImagesService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WebScraperBundle\WebScraper;

class ImagesController extends AbstractController
{
    /**
     * Color categories of the storage.
     */
    protected $matchColors = [
        'black',
        'violet',
        'blue',
        'green',
        'yellow',
        'orange',
        'red',
        'white',
    ];

    public function __construct(
        string $webpageCacheLifetime,
        protected string $publicDir,
        protected WebScraper $webScraper,
        protected ImagesService $imagesService,
    ) {
        $this->cache = new FilesystemAdapter();
        $this->imagesPublicDirectory = 'img';
        $this->webpageCacheLifetime = (float) $webpageCacheLifetime;
    }

    /**
     * Renders the homepage with the form and gallery
     */
    #[Route('/', name: 'homepage')]
    public function homepage()
    {
        return $this->render('form.html');
    }

    /**
     * Gets URL's of all stored images sorted by color.
     */
    public function getAllStored(Request $request): Response
    {
        $allImages = $this->cache->get('images.all', function (CacheItem $item) {
            return [];
        });
        $flatAllImages = [];
        foreach ($allImages as $colorName => $imgNameList) {
            if (empty($colorName)) {
                $flatAllImages = array_merge($flatAllImages, $imgNameList);
            } else {
                $flatAllImages = array_merge($imgNameList, $flatAllImages);
            }
        }
        $hostOrigin = $request->getSchemeAndHttpHost();

        return $this->json([
            'images' => array_map(function ($fileName) use ($hostOrigin) {
                $imagesDir = $this->imagesPublicDirectory;

                return FileSystem::addPath(
                    $hostOrigin, $imagesDir, $fileName
                );
            }, $flatAllImages),
        ]);
    }

    public function handleImage($img)
    {
        $imgName = $img->getImageSignature();
        $dominantImgColor = $this->imagesService->matchImageColor($img, $this->matchColors) ?? '';
        $img->setMaxHeight(200);
        $img->addCenteredText(
            $dominantImgColor,
            '#99ffff',
        );
        $allImages = $this->cache->get('images.all', function (CacheItem $item) {
            return [];
        });
        if (empty($allImages[$dominantImgColor])) {
            $allImages[$dominantImgColor] = [];
        }
        $allImages[$dominantImgColor][] = $imgName;
        $this->cache->delete('images.all');
        $this->cache->get('images.all', function (CacheItem $item) use ($allImages) {
            return $allImages;
        });

        return $img;
    }

    public function downloadImages($url, $minWidth, $minHeight)
    {
        $images = $this->webScraper
            ->getImages()
            ->setUrl($url)
            ->setMinWidth($minWidth)
            ->setMinHeight($minHeight)
            ->get();

        $allImagesItem = $this->cache->getItem("images.all");
        $allImages = $allImagesItem->get() ?? [];

        foreach ($images as $img) {
            $imgName = $img->getImageSignature();

            $this->cache->get("images.{$imgName}", function (CacheItem $item) use ($img, $imgName) {
                $img = $this->handleImage($img);
                $filePath = FileSystem::mergePath(
                    $this->publicDir,
                    $this->imagesPublicDirectory,
                    $imgName,
                );
                $img->save($filePath);

                return true;
            });
        }

        $allImagesItem->set($allImages);
        $this->cache->save($allImagesItem);
    }

    /**
     * Gets URL's of all images from storage or URL.
     */
    #[Route('/images', name: 'get_all_images')]
    public function getAll(Request $request): Response
    {
        $url = $request->query->get('url');
        $minWidth = (float) $request->query->get('min-width');
        $minHeight = (float) $request->query->get('min-height');
        $publicDirPath = $this->getParameter('public_dir');
        $images = [];

        if (empty($url)) {
            return $this->getAllStored($request);
        }

        $this->cache->get("pages.{$url}.{$minWidth}.{$minHeight}", function (CacheItem $item) use ($url, $minWidth, $minHeight) {
            $item->expiresAfter($this->webpageCacheLifetime);
            $this->downloadImages($url, $minWidth, $minHeight);

            return null;
        });

        return $this->getAllStored($request);
    }
}
