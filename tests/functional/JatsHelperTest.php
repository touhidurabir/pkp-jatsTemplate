<?php

/**
 * @file JatsHelperTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @brief JATS helper unit tests
 */

namespace APP\plugins\generic\jatsTemplate\tests\functional;

use APP\plugins\generic\jatsTemplate\classes\JatsHelper;
use DOMDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\tests\PKPTestCase;

#[CoversClass(JatsHelper::class)]
class JatsHelperTest extends PKPTestCase
{
    /**
     * Render an element via JatsHelper::htmlToJatsElement() and return its serialized XML.
     */
    private function render(string $tagName, string $html, array $attributes = [], bool $allowParagraphs = false): string
    {
        $doc = new DOMDocument();
        $root = $doc->appendChild($doc->createElement('root'));
        $root->appendChild(JatsHelper::htmlToJatsElement($doc, $tagName, $html, $attributes, $allowParagraphs));
        return $doc->saveXML($root);
    }

    /**
     * Stored rich-text HTML already has literal special characters entity-encoded - the
     * conversion must not re-escape them (e.g. "&amp;" becoming "&amp;amp;").
     */
    public function testDoesNotDoubleEscapeStoredEntities()
    {
        self::assertSame(
            '<root><article-title>Cats &amp; <bold>Dogs</bold></article-title></root>',
            $this->render('article-title', 'Cats &amp; <b>Dogs</b>')
        );
    }

    /**
     * An "&" inside a link's query string must remain correctly, singly escaped once converted
     * to a xlink:href attribute.
     */
    public function testPreservesEscapedAmpersandInLinkHref()
    {
        self::assertSame(
            '<root><bio xml:lang="en"><p>See <ext-link ext-link-type="uri" xlink:href="https://example.com/?a=1&amp;b=2">this link</ext-link></p></bio></root>',
            $this->render('bio', '<p>See <a href="https://example.com/?a=1&amp;b=2">this link</a></p>', ['xml:lang' => 'en'], allowParagraphs: true)
        );
    }

    /**
     * Elements requiring block-level content (allowParagraphs: true) must not contain bare text -
     * content with no source <p> tags is auto-wrapped in one.
     */
    public function testWrapsUnwrappedContentInParagraphWhenParagraphsAllowed()
    {
        self::assertSame(
            '<root><fn fn-type="coi-statement" id="x"><p>Plain competing interest text, no markup.</p></fn></root>',
            $this->render('fn', 'Plain competing interest text, no markup.', ['fn-type' => 'coi-statement', 'id' => 'x'], allowParagraphs: true)
        );
    }

    /**
     * Content that already has a source <p> must not be wrapped again (avoiding invalid <p><p>...</p></p>).
     */
    public function testDoesNotDoubleWrapContentThatAlreadyHasAParagraph()
    {
        self::assertSame(
            '<root><bio xml:lang="en"><p>Already a paragraph.</p></bio></root>',
            $this->render('bio', '<p>Already a paragraph.</p>', ['xml:lang' => 'en'], allowParagraphs: true)
        );
    }

    /**
     * Multiple source paragraphs are preserved as separate <p> elements, not merged into one.
     */
    public function testPreservesMultipleParagraphsSeparately()
    {
        self::assertSame(
            '<root><notes notes-type="update-notice"><p>First.</p><p>Second.</p></notes></root>',
            $this->render('notes', '<p>First.</p><p>Second.</p>', ['notes-type' => 'update-notice'], allowParagraphs: true)
        );
    }

    /**
     * Elements whose content model disallows <p> (e.g. mixed-citation, funding-statement) must
     * have any source <p> tags stripped, not preserved or auto-wrapped.
     */
    public function testStripsParagraphsWhenNotAllowed()
    {
        self::assertSame(
            '<root><mixed-citation>Some citation text.</mixed-citation></root>',
            $this->render('mixed-citation', '<p>Some citation text.</p>')
        );
    }

    /**
     * Malformed markup that fails to parse as XML falls back to a plain-escaped-text element,
     * still wrapped in a <p> when the target element requires block-level content.
     */
    public function testFallbackWrapsInParagraphWhenParagraphsAllowed()
    {
        self::assertSame(
            '<root><fn fn-type="coi-statement" id="x"><p>Broken markup &amp; nested wrong</p></fn></root>',
            $this->render('fn', 'Broken <b>markup & <i>nested wrong', ['fn-type' => 'coi-statement', 'id' => 'x'], allowParagraphs: true)
        );
    }

    /**
     * The fallback path must not wrap in a <p> when the target element's content model doesn't allow one.
     */
    public function testFallbackDoesNotWrapWhenParagraphsNotAllowed()
    {
        self::assertSame(
            '<root><mixed-citation>Broken markup &amp; nested wrong</mixed-citation></root>',
            $this->render('mixed-citation', 'Broken <b>markup & <i>nested wrong')
        );
    }
}
