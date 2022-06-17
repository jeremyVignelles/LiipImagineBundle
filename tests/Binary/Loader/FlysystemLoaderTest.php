<?php

/*
 * This file is part of the `liip/LiipImagineBundle` project.
 *
 * (c) https://github.com/liip/LiipImagineBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Liip\ImagineBundle\Tests\Binary\Loader;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use Liip\ImagineBundle\Binary\Loader\FlysystemLoader;
use Liip\ImagineBundle\Binary\Loader\LoaderInterface;
use Liip\ImagineBundle\Tests\AbstractTest;
use Symfony\Component\Mime\MimeTypes;

/**
 * @covers \Liip\ImagineBundle\Binary\Loader\FlysystemLoader
 */
class FlysystemLoaderTest extends AbstractTest
{
    private Filesystem $flyFilesystem;

    protected function setUp(): void
    {
        parent::setUp();

        if (!interface_exists(FilesystemInterface::class)) {
            $this->markTestSkipped('Requires the league/flysystem:^1.0 package.');
        }

        $this->flyFilesystem = new Filesystem(new Local($this->fixturesPath));
    }

    public function getFlysystemLoader(): FlysystemLoader
    {
        $extensionGuesser = MimeTypes::getDefault();

        return new FlysystemLoader($extensionGuesser, $this->flyFilesystem);
    }

    public function testShouldImplementLoaderInterface(): void
    {
        $this->assertInstanceOf(LoaderInterface::class, $this->getFlysystemLoader());
    }

    public function testReturnImageContentOnFind(): void
    {
        $loader = $this->getFlysystemLoader();

        $this->assertStringEqualsFile(
            $this->fixturesPath.'/assets/cats.jpeg', $loader->find('assets/cats.jpeg')->getContent()
        );
    }

    public function testThrowsIfInvalidPathGivenOnFind(): void
    {
        $this->expectException(\Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException::class);
        $this->expectExceptionMessageMatchesBC('{Source image .+ not found}');

        $loader = $this->getFlysystemLoader();

        $loader->find('invalid.jpeg');
    }
}