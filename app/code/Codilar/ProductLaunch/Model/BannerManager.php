<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Model;

use Magento\Catalog\Model\ImageUploader;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Moves uploaded banners out of tmp and builds public URLs.
 */
class BannerManager
{
    public function __construct(
        private readonly ImageUploader $imageUploader,
        private readonly Filesystem $filesystem,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return string stored file name (relative to the banner base path)
     * @throws LocalizedException
     */
    public function moveFromTmp(string $fileName): string
    {
        $fileName = basename(str_replace('\\', '/', $fileName));
        $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        if (!$media->isFile($this->imageUploader->getFilePath($this->imageUploader->getBaseTmpPath(), $fileName))) {
            throw new LocalizedException(__('The uploaded banner image could not be found. Please upload it again.'));
        }
        $relative = $this->imageUploader->moveFileFromTmp($fileName, true);
        return basename($relative);
    }

    public function getUrl(?string $banner): ?string
    {
        if (!$banner) {
            return null;
        }
        $banner = basename($banner);
        $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $path = $this->imageUploader->getFilePath($this->imageUploader->getBasePath(), $banner);
        if (!$media->isFile($path)) {
            return null;
        }
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . $path;
    }

    public function exists(?string $banner): bool
    {
        return $this->getUrl($banner) !== null;
    }

    public function getBasePath(): string
    {
        return $this->imageUploader->getBasePath();
    }
}
