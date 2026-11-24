<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Block\Launch;

class View extends AbstractLaunch
{
    public function getEntry(): ?array
    {
        return $this->getData('entry') ?: null;
    }

    protected function getEntries(): array
    {
        $entry = $this->getEntry();
        return $entry ? [$entry] : [];
    }
}
