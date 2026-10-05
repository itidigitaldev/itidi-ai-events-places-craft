<?php

namespace iti\craftitiplugin\tests\llms;

use iti\craftitiplugin\llms\LlmsTxtWriter;
use PHPUnit\Framework\TestCase;

class LlmsTxtWriterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/craft-iti-llms-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        @chmod($this->dir, 0777);
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testWrapAddsMarkers(): void
    {
        $this->assertSame(
            "<!-- BEGIN ITI Digital llms.txt -->\n# Site\n<!-- END ITI Digital llms.txt -->",
            LlmsTxtWriter::wrap('# Site')
        );
    }

    public function testMergeIntoNothingReturnsBlock(): void
    {
        $block = LlmsTxtWriter::wrap('# Site');

        $this->assertSame($block, LlmsTxtWriter::merge(null, $block, false));
    }

    public function testMergeReplacesOnlyOurBlock(): void
    {
        $existing = "# Theirs\n\n" . LlmsTxtWriter::wrap('old') . "\n\nfooter";
        $merged = LlmsTxtWriter::merge($existing, LlmsTxtWriter::wrap('new'), false);

        $this->assertSame("# Theirs\n\n" . LlmsTxtWriter::wrap('new') . "\n\nfooter", $merged);
    }

    public function testMergeAppendsToForeignFileWhenAllowed(): void
    {
        $block = LlmsTxtWriter::wrap('ours');

        $this->assertSame("# Theirs\n\n" . $block . "\n", LlmsTxtWriter::merge("# Theirs\n\n", $block, true));
    }

    public function testMergeRefusesForeignFileWhenAppendOff(): void
    {
        $this->assertNull(LlmsTxtWriter::merge('# Theirs', LlmsTxtWriter::wrap('ours'), false));
    }

    public function testMergeIsIdempotent(): void
    {
        $block = LlmsTxtWriter::wrap('ours');
        $once = LlmsTxtWriter::merge('# Theirs', $block, true);

        $this->assertSame($once, LlmsTxtWriter::merge($once, $block, true));
    }

    public function testRemoveLeavesForeignContent(): void
    {
        $content = "# Theirs\n\n" . LlmsTxtWriter::wrap('ours') . "\n";

        $this->assertSame("# Theirs\n", LlmsTxtWriter::remove($content));
    }

    public function testRemoveOfOnlyOurBlockIsEmpty(): void
    {
        $this->assertSame('', LlmsTxtWriter::remove(LlmsTxtWriter::wrap('ours') . "\n"));
    }

    public function testWriteFileCreatesNewFile(): void
    {
        $path = $this->dir . '/llms.txt';
        $status = LlmsTxtWriter::writeFile($path, LlmsTxtWriter::wrap('ours'), false);

        $this->assertSame(LlmsTxtWriter::STATUS_CREATED, $status);
        $this->assertSame(LlmsTxtWriter::wrap('ours') . "\n", file_get_contents($path));
    }

    public function testWriteFileUpdatesOurFile(): void
    {
        $path = $this->dir . '/llms.txt';
        LlmsTxtWriter::writeFile($path, LlmsTxtWriter::wrap('old'), false);

        $this->assertSame(LlmsTxtWriter::STATUS_UPDATED, LlmsTxtWriter::writeFile($path, LlmsTxtWriter::wrap('new'), false));
        $this->assertStringContainsString('new', file_get_contents($path));
        $this->assertStringNotContainsString('old', file_get_contents($path));
    }

    public function testWriteFileAppendsToForeignFile(): void
    {
        $path = $this->dir . '/llms.txt';
        file_put_contents($path, "# Theirs\n");

        $this->assertSame(LlmsTxtWriter::STATUS_APPENDED, LlmsTxtWriter::writeFile($path, LlmsTxtWriter::wrap('ours'), true));
        $this->assertStringStartsWith("# Theirs\n\n<!-- BEGIN", file_get_contents($path));
    }

    public function testWriteFileReportsConflictAndLeavesForeignFileAlone(): void
    {
        $path = $this->dir . '/llms.txt';
        file_put_contents($path, "# Theirs\n");

        $this->assertSame(LlmsTxtWriter::STATUS_CONFLICT, LlmsTxtWriter::writeFile($path, LlmsTxtWriter::wrap('ours'), false));
        $this->assertSame("# Theirs\n", file_get_contents($path));
    }

    public function testWriteFileReportsUnwritableDirectory(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('Running as root ignores directory permissions.');
        }

        chmod($this->dir, 0555);

        $this->assertSame(LlmsTxtWriter::STATUS_UNWRITABLE, LlmsTxtWriter::writeFile($this->dir . '/llms.txt', LlmsTxtWriter::wrap('ours'), false));
    }

    public function testRemoveFromFileDeletesFileThatOnlyHadOurBlock(): void
    {
        $path = $this->dir . '/llms.txt';
        LlmsTxtWriter::writeFile($path, LlmsTxtWriter::wrap('ours'), false);

        $this->assertTrue(LlmsTxtWriter::removeFromFile($path));
        $this->assertFileDoesNotExist($path);
    }

    public function testRemoveFromFileKeepsForeignContent(): void
    {
        $path = $this->dir . '/llms.txt';
        file_put_contents($path, "# Theirs\n");
        LlmsTxtWriter::writeFile($path, LlmsTxtWriter::wrap('ours'), true);

        $this->assertTrue(LlmsTxtWriter::removeFromFile($path));
        $this->assertSame("# Theirs\n", file_get_contents($path));
    }

    public function testRemoveFromFileIgnoresForeignFileWithoutMarkers(): void
    {
        $path = $this->dir . '/llms.txt';
        file_put_contents($path, "# Theirs\n");

        $this->assertTrue(LlmsTxtWriter::removeFromFile($path));
        $this->assertSame("# Theirs\n", file_get_contents($path));
    }

    public function testRemoveFromFileReturnsTrueWhenFileDoesNotExist(): void
    {
        $this->assertTrue(LlmsTxtWriter::removeFromFile($this->dir . '/missing.txt'));
    }

    public function testRemoveFromFileReturnsFalseWhenUnlinkFails(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('Running as root ignores directory permissions.');
        }

        $path = $this->dir . '/llms.txt';
        LlmsTxtWriter::writeFile($path, LlmsTxtWriter::wrap('ours'), false);
        chmod($this->dir, 0555);

        $this->assertFalse(LlmsTxtWriter::removeFromFile($path));
        $this->assertFileExists($path);
    }

    public function testRemoveFromFileReturnsFalseWhenFileCannotBeRead(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('Running as root ignores file permissions.');
        }

        $path = $this->dir . '/llms.txt';
        LlmsTxtWriter::writeFile($path, LlmsTxtWriter::wrap('ours'), false);
        chmod($path, 0222);

        $result = LlmsTxtWriter::removeFromFile($path);

        chmod($path, 0644);

        $this->assertFalse($result);
        $this->assertTrue(LlmsTxtWriter::hasBlock((string) file_get_contents($path)));
    }

    public function testWriteFileNeverOverwritesWhenExistingFileCannotBeRead(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('Running as root ignores file permissions.');
        }

        $path = $this->dir . '/llms.txt';
        file_put_contents($path, "# Theirs\n");
        // Write-only: is_writable($path) would say yes, isolating the read failure from
        // the already-covered "existing file isn't writable" case below.
        chmod($path, 0222);

        $status = LlmsTxtWriter::writeFile($path, LlmsTxtWriter::wrap('ours'), false);

        chmod($path, 0644);

        $this->assertSame(LlmsTxtWriter::STATUS_UNWRITABLE, $status);
        $this->assertSame("# Theirs\n", file_get_contents($path));
    }

    public function testWriteFileReportsUnwritableWhenExistingFileIsReadOnly(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('Running as root ignores file permissions.');
        }

        $path = $this->dir . '/llms.txt';
        file_put_contents($path, LlmsTxtWriter::wrap('old') . "\n");
        chmod($path, 0444);

        $this->assertSame(LlmsTxtWriter::STATUS_UNWRITABLE, LlmsTxtWriter::writeFile($path, LlmsTxtWriter::wrap('new'), false));
    }

    public function testWriteFileTreatsBlankExistingFileAsNew(): void
    {
        $path = $this->dir . '/llms.txt';
        file_put_contents($path, "   \n");

        $status = LlmsTxtWriter::writeFile($path, LlmsTxtWriter::wrap('ours'), false);

        $this->assertSame(LlmsTxtWriter::STATUS_CREATED, $status);
        $this->assertSame(LlmsTxtWriter::wrap('ours') . "\n", file_get_contents($path));
    }

    public function testRemovePreservesIndentationAfterBlock(): void
    {
        $content = LlmsTxtWriter::wrap('ours') . "\n    indented\n";

        $this->assertSame("    indented\n", LlmsTxtWriter::remove($content));
    }

    public function testRemoveLeavesContentBothBeforeAndAfterBlock(): void
    {
        $content = "# Theirs\n\n" . LlmsTxtWriter::wrap('old') . "\n\nfooter";

        $this->assertSame("# Theirs\n\nfooter", LlmsTxtWriter::remove($content));
    }

    public function testRemoveAnchorsOnBlockClosestToEndMarkerWhenAnOrphanedBeginExists(): void
    {
        $content = "# Theirs\n<!-- BEGIN ITI Digital llms.txt -->\nnote\n\n" . LlmsTxtWriter::wrap('ours');

        $this->assertSame(
            "# Theirs\n<!-- BEGIN ITI Digital llms.txt -->\nnote",
            LlmsTxtWriter::remove($content)
        );
    }

    public function testHasBlockIgnoresAnOrphanedEndMarkerBeforeTheRealBlock(): void
    {
        $content = "<!-- END ITI Digital llms.txt -->\n# Theirs\n\n" . LlmsTxtWriter::wrap('ours') . "\n";

        $this->assertTrue(LlmsTxtWriter::hasBlock($content));
        $this->assertSame(
            "<!-- END ITI Digital llms.txt -->\n# Theirs\n",
            LlmsTxtWriter::remove($content)
        );
    }

    public function testHasBlockTrueWhenBothMarkersPresent(): void
    {
        $this->assertTrue(LlmsTxtWriter::hasBlock(LlmsTxtWriter::wrap('ours')));
    }

    public function testHasBlockFalseWithoutMarkers(): void
    {
        $this->assertFalse(LlmsTxtWriter::hasBlock('# Theirs'));
    }
}
