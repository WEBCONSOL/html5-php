<?php
/**
 * @file
 * Test the Tree Builder.
 */
namespace HTML5\Parser;

use HTML5\Elements;

require_once __DIR__ . '/../TestCase.php';

/**
 * These tests are functional, not necessarily unit tests.
 */
class DOMTreeBuilderTest extends \HTML5\Tests\TestCase {

  /**
   * Convenience function for parsing.
   */
  protected function parse($string) {
    $treeBuilder = new DOMTreeBuilder();
    $input = new StringInputStream($string);
    $scanner = new Scanner($input);
    $parser = new Tokenizer($scanner, $treeBuilder);

    $parser->parse();

    return $treeBuilder->document();
  }

  /**
   * Utility function for parsing a fragment of HTML5.
   */
  protected function parseFragment($string) {
    $treeBuilder = new DOMTreeBuilder(TRUE);
    $input = new StringInputStream($string);
    $scanner = new Scanner($input);
    $parser = new Tokenizer($scanner, $treeBuilder);

    $parser->parse();

    return $treeBuilder->fragment();
  }

  public function testDocument() {
    $html = "<!DOCTYPE html><html></html>";
    $doc = $this->parse($html);

    $this->assertInstanceOf('\DOMDocument', $doc);
    $this->assertEquals('html', $doc->documentElement->tagName);
  }

  public function testFragment() {
    $html = "<div>test</div><span>test2</span>";
    $doc = $this->parseFragment($html);

    $this->assertInstanceOf('\DOMDocumentFragment', $doc);
    $this->assertTrue($doc->hasChildNodes());
    $this->assertEquals('div', $doc->childNodes->item(0)->tagName);
    $this->assertEquals('test', $doc->childNodes->item(0)->textContent);
    $this->assertEquals('span', $doc->childNodes->item(1)->tagName);
    $this->assertEquals('test2', $doc->childNodes->item(1)->textContent);
  }

  public function testElements() {
    $html = "<!DOCTYPE html><html><head><title></title></head><body></body></html>";
    $doc = $this->parse($html);
    $root = $doc->documentElement;

    $this->assertEquals('html', $root->tagName);
    $this->assertEquals('html', $root->localName);
    $this->assertEquals('html', $root->nodeName);

    $this->assertEquals(2, $root->childNodes->length);
    $kids = $root->childNodes;

    $this->assertEquals('head', $kids->item(0)->tagName);
    $this->assertEquals('body', $kids->item(1)->tagName);

    $head = $kids->item(0);
    $this->assertEquals(1, $head->childNodes->length);
    $this->assertEquals('title', $head->childNodes->item(0)->tagName);
  }

  public function testAttributes() {
    $html = "<!DOCTYPE html>
      <html>
      <head><title></title></head>
      <body id='a' class='b c'></body>
      </html>";
    $doc = $this->parse($html);
    $root = $doc->documentElement;

    $body = $root->GetElementsByTagName('body')->item(0);
    $this->assertEquals('body', $body->tagName);
    $this->assertTrue($body->hasAttributes());
    $this->assertEquals('a', $body->getAttribute('id'));
    $this->assertEquals('b c', $body->getAttribute('class'));

    $body2 = $doc->getElementById('a');
    $this->assertEquals('body', $body2->tagName);
    $this->assertEquals('a', $body2->getAttribute('id'));
  }

  public function testSVGAttributes() {
    $html = "<!DOCTYPE html>
      <html><body>
      <svg width='150' viewbox='2'>
      <rect textlength='2'/>
      <animatecolor>foo</animatecolor>
      </svg> 
      </body></html>";
    $doc = $this->parse($html);
    $root = $doc->documentElement;

    $svg = $root->getElementsByTagName('svg')->item(0);
    $this->assertTrue($svg->hasAttribute('viewBox'));

    $rect = $root->getElementsByTagName('rect')->item(0);
    $this->assertTrue($rect->hasAttribute('textLength'));

    $ac = $root->getElementsByTagName('animateColor');
    $this->assertEquals(1, $ac->length);
  }

  public function testMathMLAttribute() {
    $html = '<!doctype html>
      <html lang="en">
        <body>
          <math>
            <mi>x</mi>
            <csymbol definitionurl="http://www.example.com/mathops/multiops.html#plusminus">
              <mo>&PlusMinus;</mo>
            </csymbol>
            <mi>y</mi>
          </math>
        </body>
      </html>';

    $doc = $this->parse($html);
    $root = $doc->documentElement;

    $csymbol = $root->getElementsByTagName('csymbol')->item(0);
    $this->assertTrue($csymbol->hasAttribute('definitionURL'));
  }

  public function testMissingHtmlTag() {
    $html = "<!DOCTYPE html><title>test</title>";
    $doc = $this->parse($html);

    $this->assertEquals('html', $doc->documentElement->tagName);
    $this->assertEquals('title', $doc->documentElement->childNodes->item(0)->tagName);
  }

  public function testComment() {
    $html = '<html><!--Hello World.--></html>';

    $doc = $this->parse($html);

    $comment = $doc->documentElement->childNodes->item(0);
    $this->assertEquals(XML_COMMENT_NODE, $comment->nodeType);
    $this->assertEquals("Hello World.", $comment->data);


    $html = '<!--Hello World.--><html></html>';
    $doc = $this->parse($html);

    $comment = $doc->childNodes->item(1);
    $this->assertEquals(XML_COMMENT_NODE, $comment->nodeType);
    $this->assertEquals("Hello World.", $comment->data);

    $comment = $doc->childNodes->item(2);
    $this->assertEquals(XML_ELEMENT_NODE, $comment->nodeType);
    $this->assertEquals("html", $comment->tagName);
  }

  public function testCDATA() {
    $html = "<!DOCTYPE html><html><math><![CDATA[test]]></math></html>";
    $doc = $this->parse($html);

    $wrapper = $doc->getElementsByTagName('math')->item(0);
    $this->assertEquals(1, $wrapper->childNodes->length);
    $cdata = $wrapper->childNodes->item(0);
    $this->assertEquals(XML_CDATA_SECTION_NODE, $cdata->nodeType);
    $this->assertEquals('test', $cdata->data);
  }

  public function testText() {
    $html = "<!DOCTYPE html><html><head></head><body><math>test</math></body></html>";
    $doc = $this->parse($html);

    $wrapper = $doc->getElementsByTagName('math')->item(0);
    $this->assertEquals(1, $wrapper->childNodes->length);
    $data = $wrapper->childNodes->item(0);
    $this->assertEquals(XML_TEXT_NODE, $data->nodeType);
    $this->assertEquals('test', $data->data);
  }

  public function testParseErrors() {
    $html = "<!DOCTYPE html><html><math><![CDATA[test";
    $doc = $this->parse($html);

    // We're JUST testing that we can access errors. Actual testing of 
    // error messages happen in the Tokenizer's tests.
    $this->assertGreaterThan(0,  count($doc->errors));
    $this->assertTrue(is_string($doc->errors[0]));
  }

  public function testProcessingInstruction() {
    // Test the simple case, which is where PIs are inserted into the DOM.
    $doc = $this->parse('<!DOCTYPE html><html><?foo bar?>');
    $this->assertEquals(1, $doc->documentElement->childNodes->length);
    $pi = $doc->documentElement->firstChild;
    $this->assertInstanceOf('\DOMProcessingInstruction', $pi);
    $this->assertEquals('foo', $pi->nodeName);
    $this->assertEquals('bar', $pi->data);

    // Leading xml PIs should be ignored.
    $doc = $this->parse('<?xml version="1.0"?><!DOCTYPE html><html><head></head></html>');

    $this->assertEquals(2, $doc->childNodes->length);
    $this->assertInstanceOf('\DOMDocumentType', $doc->childNodes->item(0));
    $this->assertInstanceOf('\DOMElement', $doc->childNodes->item(1));
  }

  public function testAutocloseP() {
    $html = "<!DOCTYPE html><html><body><p><figure></body></html>";
    $doc = $this->parse($html);

    $p = $doc->getElementsByTagName('p')->item(0);
    $this->assertEquals(0, $p->childNodes->length);
    $this->assertEquals('figure', $p->nextSibling->tagName);
  }

  public function testAutocloseLI() {
    $this->markTestIncomplete("Incomplete.");
  }

  public function testMathML() {
    $html = '<!doctype html>
      <html lang="en">
        <body>
          <math xmlns="http://www.w3.org/1998/Math/MathML">
            <mi>x</mi>
            <csymbol definitionurl="http://www.example.com/mathops/multiops.html#plusminus">
              <mo>&PlusMinus;</mo>
            </csymbol>
            <mi>y</mi>
          </math>
        </body>
      </html>';

    $doc = $this->parse($html);
    $math = $doc->getElementsByTagName('math')->item(0);
    $this->assertEquals('math', $math->tagName);
    $this->assertEquals('math', $math->nodeName);
    $this->assertEquals('math', $math->localName);
    $this->assertEmpty($math->namespaceURI);
  }

  public function testSVG() {
    $html = '<!doctype html>
      <html lang="en">
        <body>
          <svg width="150" height="100" viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg">
            <rect width="1" height="2" x="2" fill="#d2232c" />
            <text font-family="Verdana" font-size="32">
              <textpath xlink:href="#Foo">
                Test Text.
              </textPath>
            </text>
          </svg>
        </body>
      </html>';

    $doc = $this->parse($html);
    $svg = $doc->getElementsByTagName('svg')->item(0);
    $this->assertEquals('svg', $svg->tagName);
    $this->assertEquals('svg', $svg->nodeName);
    $this->assertEquals('svg', $svg->localName);
    $this->assertEmpty($svg->namespaceURI);

    $textPath = $doc->getElementsByTagName('textPath')->item(0);
    $this->assertEquals('textPath', $textPath->tagName);
  }
}
