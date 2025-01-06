<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Async\Twig;

use Twig\Loader\FilesystemLoader;
use Twig\Source;

use function Amp\File\read;

final class AsyncFileSystemLoader extends FilesystemLoader
{
    public function getSourceContext(string $name): Source
    {
        if (null === $path = $this->findTemplate($name)) {
            return new Source('', $name, '');
        }

        return new Source(read($path), $name, $path);
    }
}
