<?php

declare(strict_types=1);

/*
 * This file is part of the ekino Drupal Debug project.
 *
 * (c) ekino
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ekino\Drupal\Debug\Tests\Unit\Kernel\Helper;

use Carbon\Carbon;
use Composer\Autoload\ClassLoader;
use Drupal\Core\DrupalKernel;
use Drupal\Core\OriginalDrupalKernel;
use Ekino\Drupal\Debug\Exception\NotSupportedException;
use Ekino\Drupal\Debug\Kernel\DebugKernel;
use Ekino\Drupal\Debug\Kernel\Helper\OriginalDrupalKernelHelper;
use Ekino\Drupal\Debug\Resource\Model\ResourcesCollection;
use Ekino\Drupal\Debug\Resource\ResourcesFreshnessChecker;
use Ekino\Drupal\Debug\Tests\Traits\FileHelperTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Filesystem\Filesystem;

class OriginalDrupalKernelHelperTest extends TestCase
{
    use FileHelperTrait;

    /**
     * @var string
     */
    const CACHE_DIRECTORY = __DIR__.'/cache';

    /**
     * @var string
     */
    const SUBSTITUTE_FILE_PATH = self::CACHE_DIRECTORY.'/OriginalDrupalKernel.php';

    /**
     * @var string
     */
    const SUBSTITUTE_FRESHNESS_META_FILE_PATH = self::SUBSTITUTE_FILE_PATH.'.meta';

    /**
     * @var string
     */
    const CANNOT_BE_READ_FILE_PATH = __DIR__.'/fixtures/__cannot_be_read.php';

    /**
     * @var string
     */
    const NO_REPLACEMENTS_FILE_PATH = __DIR__.'/fixtures/no_replacements.php';

    /**
     * @var string
     */
    const CLASS_ALIAS_FAIL_FILE_PATH = __DIR__.'/test_classes/TestOtherKernel.php';

    /**
     * @var string
     */
    const ORIGINAL_DRUPAL_KERNEL_FILE_PATH = __DIR__.'/fixtures/OriginalDrupalKernel.php';

    /**
     * @var ClassLoader|MockObject
     */
    private $classLoader;

    /**
     * @var int|null
     */
    private $inOneYearTimestamp;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->inOneYearTimestamp = Carbon::now()->addYear()->getTimestamp();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        foreach (array(
            self::SUBSTITUTE_FILE_PATH,
            self::SUBSTITUTE_FRESHNESS_META_FILE_PATH,
            self::CANNOT_BE_READ_FILE_PATH,
        ) as $filePath) {
            self::deleteFile($filePath);
        }
    }

    public function testSubstituteWhenTheOriginalFileIsNotFound(): void
    {
        $this->setUpOriginalFilePathAndFreshness(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The original DrupalKernel class file could not be found.');

        $this->callSubstitute();
    }

    public function testSubstituteWhenTheSubstituteIsNotFreshAndTheOriginalFileCannotBeRead(): void
    {
        $filesystem = new Filesystem();
        $filesystem->dumpFile(self::CANNOT_BE_READ_FILE_PATH, '');
        $filesystem->chmod(self::CANNOT_BE_READ_FILE_PATH, 0000);

        $this->assertFileNotIsReadable(self::CANNOT_BE_READ_FILE_PATH);

        $this->setUpOriginalFilePathAndFreshness(self::CANNOT_BE_READ_FILE_PATH, false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The original DrupalKernel content could not be read.');

        $this->callSubstitute();
    }

    public function testSubstituteWhenTheSubstituteIsNotFreshAndThereIsMoreReplacementsInTheOriginalFileThanExpected(): void
    {
        $this->setUpOriginalFilePathAndFreshness(self::NO_REPLACEMENTS_FILE_PATH, false);

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('There should be strictly 2 replacements done in the original DrupalKernel substitute.');

        $this->callSubstitute();
    }

    /**
     * @runInSeparateProcess
     */
    public function testSubstituteWhenTheSubstituteIsNotFreshAndTheClassAliasFail(): void
    {
        $this->setUpOriginalFilePathAndFreshness('original', false);

        $this->setUpAndExpectClassAliasFail();

        try {
            $this->callSubstitute();
        } finally {
            $this->assertThatTheSubstituteFileWasCreated();

            $this->assertThatTheSubstituteFreshnessMetaFileWasCommitted();
        }
    }

    /**
     * @runInSeparateProcess
     */
    public function testSubstituteWhenTheSubstituteIsNotFresh(): void
    {
        $this->setUpOriginalFilePathAndFreshness('original', false);

        $this->callSubstitute();

        $this->assertThatTheSubstituteFileWasCreated();

        $this->assertThatTheSubstituteFreshnessMetaFileWasCommitted();

        $this->assertThatTheOriginalDrupalKernelWasSubstituted();
    }

    /**
     * @runInSeparateProcess
     */
    public function testSubstituteWhenTheSubstituteIsFreshButTheClassAliasFail(): void
    {
        $this->setUpOriginalFilePathAndFreshness('original', true);

        $this->setUpAndExpectClassAliasFail();

        $this->callSubstitute();

        $this->assertThatTheSubstituteFileWasNotModified();

        $this->assertThatTheSubstituteFreshnessMetaFileWasNotModified();
    }

    /**
     * @runInSeparateProcess
     */
    public function testSubstituteWhenTheSubstituteIsFresh(): void
    {
        $this->setUpOriginalFilePathAndFreshness('original', true);

        $this->callSubstitute();

        $this->assertThatTheSubstituteFileWasNotModified();

        $this->assertThatTheSubstituteFreshnessMetaFileWasNotModified();

        $this->assertThatTheOriginalDrupalKernelWasSubstituted();
    }

    private function callSubstitute(): void
    {
        OriginalDrupalKernelHelper::substitute($this->classLoader, self::CACHE_DIRECTORY);
    }

    /**
     * @param string|null $filePath
     * @param bool        $fresh
     */
    private function setUpOriginalFilePathAndFreshness(?string $filePath, bool $fresh = false): void
    {
        if ('original' === $filePath) {
            $filePath = \realpath(\sprintf('%s/../../../../../vendor/drupal/core/lib/Drupal/Core/DrupalKernel.php', __DIR__));
            if (!\is_string($filePath)) {
                $this->fail(\sprintf('The original DrupalKernel class file could not be found.'));
            }
        }

        $this->classLoader = $this->createMock(ClassLoader::class);
        $this->classLoader
            ->expects($this->atLeastOnce())
            ->method('findFile')
            ->with('Drupal\Core\DrupalKernel')
            ->willReturn($filePath);

        if (!\is_string($filePath)) {
            return;
        }

        if ($fresh) {
            self::copyFile(self::ORIGINAL_DRUPAL_KERNEL_FILE_PATH, self::SUBSTITUTE_FILE_PATH);

            $resourcesFreshnessChecker = new ResourcesFreshnessChecker(self::SUBSTITUTE_FRESHNESS_META_FILE_PATH, new ResourcesCollection(array(
                new FileExistenceResource(self::SUBSTITUTE_FILE_PATH),
                new FileResource($filePath),
                new FileResource(\sprintf('%s/../../../../../src/Kernel/DebugKernel.php', __DIR__)),
            )));
            $resourcesFreshnessChecker->commit();
            if (!$resourcesFreshnessChecker->isFresh()) {
                $this->fail('The substitute should be fresh.');
            }

            if (!\is_int($this->inOneYearTimestamp)) {
                $this->fail('The timestamp should be set.');
            }

            foreach (array(self::SUBSTITUTE_FILE_PATH, self::SUBSTITUTE_FRESHNESS_META_FILE_PATH) as $filePath) {
                self::touchFile($filePath, $this->inOneYearTimestamp);
            }
        } else {
            self::deleteFile(self::SUBSTITUTE_FRESHNESS_META_FILE_PATH, true);
        }
    }

    private function setUpAndExpectClassAliasFail(): void
    {
        require self::CLASS_ALIAS_FAIL_FILE_PATH;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The DebugKernel class could not be aliased.');
    }

    private function assertThatTheSubstituteFileWasCreated(): void
    {
        $this->assertFileExists(self::SUBSTITUTE_FILE_PATH);

        $this->assertTrue(\class_exists('Drupal\Core\OriginalDrupalKernel'));

        $refl = new \ReflectionMethod(OriginalDrupalKernel::class, 'guessApplicationRoot');
        $refl->setAccessible(true);
        $this->assertSame(\realpath(\sprintf('%s/../../../../../vendor/drupal', __DIR__)), $refl->invoke(null));
    }

    private function assertThatTheOriginalDrupalKernelWasSubstituted(): void
    {
        $this->assertSame(DebugKernel::class, (new \ReflectionClass(DrupalKernel::class))->getName());
    }

    private function assertThatTheSubstituteFreshnessMetaFileWasCommitted(): void
    {
        $this->assertFileExists(self::SUBSTITUTE_FRESHNESS_META_FILE_PATH);
    }

    private function assertThatTheSubstituteFileWasNotModified(): void
    {
        $this->assertSame($this->inOneYearTimestamp, \filemtime(self::SUBSTITUTE_FILE_PATH));
    }

    private function assertThatTheSubstituteFreshnessMetaFileWasNotModified(): void
    {
        $this->assertSame($this->inOneYearTimestamp, \filemtime(self::SUBSTITUTE_FRESHNESS_META_FILE_PATH));
    }
}
