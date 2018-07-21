<?php

/**
 * @covers DisplayTitleHooks::onHtmlPageLinkRendererBegin
 * @covers DisplayTitleHooks::onSelfLinkBegin
 * @group Database
 */
class DisplayTitleTest extends MediaWikiTestCase {

	/**
	 * @dataProvider provideTestData
	 */
	public function testParse( $testName, $pageName, $linkText, $testPages ) {
		$testPage = $testPages[0];

		for ( end( $testPages ); key( $testPages ) !== null; prev( $testPages ) ) {
			$page = current( $testPages );
			if ( !$page['selfLink'] ) {
				$name = $page['name'];
				$redirectName = $page['redirectName'];
				$displaytitle = $page['displaytitle'];
				$this->createTestPage( $name, $redirectName, $displaytitle );
			}
		}

		$expectedHtml = $this->getExpectedHtml( $pageName, $linkText, $testPages );

		$actualHtml = $this->getActualHtml(
			$testPage['selfLink'] ? $testPage['name'] : 'Test Page', $pageName,
			$linkText );

		$this->assertEquals( $expectedHtml, $actualHtml, $testName );
	}

	/**
	 * Create a test page.
	 * @param string $name The page name
	 * @param string|null $redirectName The page name of the page this page is
	 *	redirecting to
	 * @param string|null $displaytitle The page displaytitle (ignored if page
	 *	is a redirect)
	 */
	private function createTestPage( $name, $redirectName, $displaytitle ) {
		$title = Title::newFromText( $name );
		$page = new WikiPage( $title );
		if ( !is_null( $redirectName ) ) {
			$wikitext = '#REDIRECT [[' . $redirectName . ']]';
		} else {
			$wikitext = 'This is a test';
			if ( !is_null( $displaytitle ) ) {
				$wikitext .= "{{DISPLAYTITLE:$displaytitle}}";
			}
		}
		$page->doEditContent( new WikitextContent( $wikitext ), '' );
	}

	/**
	 * Get expected HTML for test.
	 * @param string $pageName The name of the page in the test
	 * @param string|null $linkText The link text
	 * @param array $testPages The array of information about the test pages
	 */
	private function getExpectedHtml( $pageName, $linkText, $testPages ) {
		$name = $testPages[0]['name'];
		if ( $testPages[0]['selfLink'] ) {
			$displaytitle = $testPages[0]['displaytitle'];
			if ( is_null( $linkText ) || $linkText === $name ) {
				if ( $pageName === $this->lcfirstPageName( $name ) &&
					is_null( $linkText ) ) {
					$linkText = $pageName;
				} elseif ( !is_null( $displaytitle ) ) {
					$linkText = $displaytitle;
				} elseif ( is_null( $linkText ) ) {
					$linkText = $name;
				}
			}
			$html = <<<EOT
<div class="mw-parser-output"><p><a class="mw-selflink selflink">$linkText</a>
</p></div>
EOT;
		} else {
			$isRedirect = !is_null( $testPages[0]['redirectName'] );
			$title = Title::newFromText( $name );
			$url = $title->getLocalURL();
			if ( is_null( $linkText ) || $linkText === $name ) {
				if ( $pageName === $this->lcfirstPageName( $name ) &&
					is_null( $linkText ) ) {
						$linkText = $pageName;
				} else {
					if ( $isRedirect ) {
						$displaytitle = $testPages[1]['displaytitle'];
					} else {
						$displaytitle = $testPages[0]['displaytitle'];
					}
					if ( is_null( $displaytitle ) ) {
						if ( $isRedirect ) {
							$linkText = $testPages[1]['name'];
						} else {
							$linkText = $name;
						}
					} else {
						$linkText = $displaytitle;
					}
				}
			}
			if ( $isRedirect ) {
				$redirectClass = ' class="mw-redirect"';
			} else {
				$redirectClass = '';
			}
			$html = <<<EOT
<div class="mw-parser-output"><p><a href="$url"$redirectClass title="$name">$linkText</a>
</p></div>
EOT;
		}
		return $html;
	}

	/**
	 * Get actual HTML for test.
	 * @param string $testPageName The name of the test page
	 * @param string $pageName The name of the page in the test
	 * @param string|null $linkText The link text
	 */
	private function getActualHtml( $testPageName, $pageName, $linkText ) {
		$wikitext = '[[';
		if ( is_null( $pageName ) ) {
			$wikitext .= $testPageName;
		} else {
			$wikitext .= $pageName;
		}
		if ( !is_null( $linkText ) ) {
			$wikitext .= '|' . $linkText;
		}
		$wikitext .= ']]';
		$content = new WikitextContent( $wikitext );
		$parserOptions = new ParserOptions( $this->getTestUser()->getUser() );
		$parserOptions->setRemoveComments( true );
		$title = Title::newFromText( $testPageName );
		$parserOutput = $content->getParserOutput( $title, null, $parserOptions );
		$html = $parserOutput->getText();
		return $html;
	}

	public $tests = [];

	public function provideTestData() {
		$this->setMwGlobals( 'wgAllowDisplayTitle', true );
		$this->setMwGlobals( 'wgRestrictDisplayTitle', false );

		$pageWithoutDisplaytitle = [
			'name' => 'Page without displaytitle',
			'redirectName' => null,
			'displaytitle' => null,
			'selfLink' => false
		];

		$pageWithDisplaytitle = [
			'name' => 'Page with displaytitle',
			'redirectName' => null,
			'displaytitle' => 'My displaytitle',
			'selfLink' => false
		];

		$redirectToPageWithoutDisplaytitle = [
			'name' => 'Redirect to page without displaytitle',
			'redirectName' => 'Page without displaytitle',
			'displaytitle' => null,
			'selfLink' => false
		];

		$redirectToPageWithDisplaytitle = [
			'name' => 'Redirect to page with displaytitle',
			'redirectName' => 'Page with displaytitle',
			'displaytitle' => null,
			'selfLink' => false
		];

		$pageWithoutDisplaytitleWithSelfLink = [
			'name' => 'Page without displaytitle',
			'redirectName' => null,
			'displaytitle' => null,
			'selfLink' => true
		];

		$pageWithDisplaytitleWithSelfLink = [
			'name' => 'Page with displaytitle',
			'redirectName' => null,
			'displaytitle' => 'My displaytitle',
			'selfLink' => true
		];

		$userPageWithoutDisplaytitle = [
			'name' => 'User:Page without displaytitle',
			'redirectName' => null,
			'displaytitle' => null,
			'selfLink' => false
		];

		$userPageWithDisplaytitle = [
			'name' => 'User:Page with displaytitle',
			'redirectName' => null,
			'displaytitle' => 'My displaytitle',
			'selfLink' => false
		];

		$this->addTests( [
			$pageWithoutDisplaytitle
			] );
		$this->addTests( [
			$pageWithDisplaytitle
			] );
		$this->addTests( [
			$redirectToPageWithoutDisplaytitle,
			$pageWithoutDisplaytitle
			] );
		$this->addTests( [
			$redirectToPageWithDisplaytitle,
			$pageWithDisplaytitle
			] );
		$this->addTests( [
			$pageWithoutDisplaytitleWithSelfLink
			] );
		$this->addTests( [
			$pageWithDisplaytitleWithSelfLink
			] );
		$this->addTests( [
			$userPageWithoutDisplaytitle
			] );
		$this->addTests( [
			$userPageWithDisplaytitle
			] );

		return $this->tests;
	}

	private function lcfirstPageName( $name ) {
		$pieces = explode( ':', $name );
		if ( count( $pieces ) > 1 ) {
			return $pieces[0] . ':' . lcfirst( $pieces[1] );
		} else {
			return lcfirst( $name );
		}
	}

	/**
	 * Add tests for a given test page to the array of tests.
	 * @param array $testPages The array of test pages
	 */
	private function addTests( $testPages ) {
		$name = $testPages[0]['name'];
		$lcname = $this->lcfirstPageName( $name );

		$test = [];
		$test['testName'] = "Link to $name, no link text";
		$test['pageName'] = $name;
		$test['linkText'] = null;
		$test['testPages'] = $testPages;
		$this->tests[] = $test;

		$test = [];
		$test['testName'] = "Link to $name, lcfirst page name, no link text";
		$test['pageName'] = $lcname;
		$test['linkText'] = null;
		$test['testPages'] = $testPages;
		$this->tests[] = $test;

		$test = [];
		$test['testName'] = "Link to $name, page name link text";
		$test['pageName'] = $name;
		$test['linkText'] = $name;
		$test['testPages'] = $testPages;
		$this->tests[] = $test;

		$test = [];
		$test['testName'] = "Link to $name, lcfirst page name, page name link text";
		$test['pageName'] = $lcname;
		$test['linkText'] = $name;
		$test['testPages'] = $testPages;
		$this->tests[] = $test;

		$test = [];
		$test['testName'] = "Link to $name, lcfirst page name link text";
		$test['pageName'] = $name;
		$test['linkText'] = $lcname;
		$test['testPages'] = $testPages;
		$this->tests[] = $test;

		$test = [];
		$test['testName'] =
			"Link to $name, lcfirst page name, lcfirst page name link text";
		$test['pageName'] = $lcname;
		$test['linkText'] = $lcname;
		$test['testPages'] = $testPages;
		$this->tests[] = $test;

		$test = [];
		$test['testName'] = "Link to $name, link text";
		$test['pageName'] = $name;
		$test['linkText'] = 'abc';
		$test['testPages'] = $testPages;
		$this->tests[] = $test;

		$test = [];
		$test['testName'] = "Link to $name, lcfirst page name, link text";
		$test['pageName'] = $lcname;
		$test['linkText'] = 'abc';
		$test['testPages'] = $testPages;
		$this->tests[] = $test;
	}
}
