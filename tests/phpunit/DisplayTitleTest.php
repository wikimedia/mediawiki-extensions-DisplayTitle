<?php

/**
 * @covers DisplayTitleHooks
 */
class DisplayTitleTest extends MediaWikiTestCase {

	protected function setUp() {
		parent::setUp();
		$this->setMwGlobals( 'wgAllowDisplayTitle', true );
		$this->setMwGlobals( 'wgRestrictDisplayTitle', false );
	}

	/**
	 * Set the wikitext contents of a test page.
	 * @param string|Title $title The title of the page.
	 * @param string $wikitext The page contents.
	 * @return WikiPage
	 */
	protected function setPageContent( $title, $wikitext ) {
		if ( is_string( $title ) ) {
			$title = Title::newFromText( $title );
		}
		$page = new WikiPage( $title );
		$page->doEditContent( new WikitextContent( $wikitext ), '' );
		return $page;
	}

	/**
	 * @dataProvider provideCorrectRetrievalProvider
	 */
	public function testCorrectRetrieval( $p1Title, $p1Display, $p2Title, $p2Text, $p2HtmlPattern ) {
		// First page has a custom display title.
		$wikiText1 = "{{DISPLAYTITLE:$p1Display}}";
		$this->setPageContent( $p1Title, $wikiText1 );

		// Second page has a link to the first page.
		$this->setPageContent( $p2Title, $p2Text );
		$secondPageTitle = Title::newFromText( $p2Title );

		// Make sure the HTML of the second page is what we expect.
		$secondPage = WikiPage::factory( $secondPageTitle );
		$parserOptions = new ParserOptions( $this->getTestUser()->getUser() );
		$parserOptions->setRemoveComments( true );
		$this->assertRegExp(
			'|.*'.$p2HtmlPattern.'.*|',
			$secondPage->getContent()->getParserOutput( $secondPageTitle, null, $parserOptions )->getText()
		);
	}

	public function provideCorrectRetrievalProvider() {
		return [
			[
				'p1Title' => 'FirstPage',
				'p1Display' => 'The first page',
				'p2Title' => 'SecondPage',
				'p2Text' => 'Link to [[FirstPage]]',
				'p2HtmlPattern' => 'Link to <a href=.* title="FirstPage">The first page</a>'
			],
			[
				'p1Title' => 'Page3',
				'p1Display' => "The ''third'' page",
				'p2Title' => 'ForthPage',
				'p2Text' => 'Link to [[Page3]]',
				'p2HtmlPattern' => 'Link to <a href=.* title="Page3">The <i>third</i> page</a>'
			]
		];
	}
}
