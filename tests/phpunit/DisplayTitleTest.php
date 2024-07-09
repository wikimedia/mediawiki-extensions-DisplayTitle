<?php

namespace MediaWiki\Extension\DisplayTitle\Tests;

use CommentStoreComment;
use MediaWikiIntegrationTestCase;
use ParserOptions;
use RequestContext;
use Title;
use WikitextContent;

/**
 * @covers \MediaWiki\Extension\DisplayTitle\DisplayTitleHooks::onHtmlPageLinkRendererBegin
 * @covers \MediaWiki\Extension\DisplayTitle\DisplayTitleHooks::onSelfLinkBegin
 * @group Database
 *
 * Elephant Page = regular content page (no display title)
 * Redirect To Elephant Page = redirect to Elephant Page
 * User:Elephant Page = user page (no display title)
 * Snake Page = self link page (no display title)
 * Dingo Page = regular content page (display title)
 * Redirect To Dingo Page = redirect to Dingo Page
 * User:Dingo Page = user page (display title)
 * Sable Page = self link page (display title)
 */
class DisplayTitleTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			'AllowDisplayTitle' => true,
			'RestrictDisplayTitle' => false
		] );
		RequestContext::getMain()->setTitle( Title::newFromText( 'Main Page' ) );
	}

	/**
	 * @dataProvider provideTestLinks
	 */
	public function testLinks( $testName, $titleText, $wikitext, $extraPages, $expectedLinkText ) {
		Title::clearCaches();
		$wikiPageFactory = $this->getServiceContainer()->getWikiPageFactory();

		$user = $this->getTestSysop()->getUser();
		foreach ( $extraPages as $extraTitle => $extraWikitext ) {
			$page = $wikiPageFactory->newFromTitle( Title::newFromText( $extraTitle ) );
			$updater = $page->newPageUpdater( $user );
			$updater->setContent( 'main', new WikitextContent( $extraWikitext ) );
			$updater->saveRevision(
				CommentStoreComment::newUnsavedComment( 'new test page' ),
				EDIT_AUTOSUMMARY
			);
		}

		$title = Title::newFromText( $titleText );
		$content = new WikitextContent( $wikitext );
		$parserOptions = new ParserOptions( $user );
		$parserOptions->setRemoveComments( true );
		$contentRenderer = $this->getServiceContainer()->getContentRenderer();
		$parserOutput = $contentRenderer->getParserOutput( $content, $title->toPageIdentity(), null, $parserOptions );
		$actual = $parserOutput->getText();

		$this->assertStringContainsString( '>' . $expectedLinkText . '</a>', $actual, $testName );
	}

	public function provideTestLinks() {
		// link to content page without display title
		$extraPages = [
			'Elephant Page' => 'Content'
		];

		yield [
			'Link to page without display title, no link text',
			'Test Page',
			'[[Elephant Page]]',
			$extraPages,
			'Elephant Page'
		];

		yield [
			'Link to page without display title, lower case page title, no link text',
			'Test Page',
			'[[elephant Page]]',
			$extraPages,
			'elephant Page'
		];

		yield [
			'Link to page without display title, fragment, no link text',
			'Test Page',
			'[[Elephant Page#Fragment]]',
			$extraPages,
			'Elephant Page'
		];

		yield [
			'Link to page without display title, page name link text',
			'Test Page',
			'[[Elephant Page|Elephant Page]]',
			$extraPages,
			'Elephant Page'
		];

		yield [
			'Link to page without display title, page name with underscores link text',
			'Test Page',
			'[[Elephant Page|Elephant_Page]]',
			$extraPages,
			'Elephant_Page'
		];

		yield [
			'Link to page without display title, lowercase page name, page name link text',
			'Test Page',
			'[[elephant Page|Elephant Page]]',
			$extraPages,
			'Elephant Page'
		];

		yield [
			'Link to page without display title, lowercase page name, lowercase page name link text',
			'Test Page',
			'[[elephant Page|elephant Page]]',
			$extraPages,
			'elephant Page'
		];

		yield [
			'Link to page without display title, lowercase page name link text',
			'Test Page',
			'[[Elephant Page|elephant Page]]',
			$extraPages,
			'elephant Page'
		];

		yield [
			'Link to page without display title, other link text',
			'Test Page',
			'[[Elephant Page|Coyote]]',
			$extraPages,
			'Coyote'
		];

		yield [
			'Link to page without display title, lowercase page name, other link text',
			'Test Page',
			'[[elephant Page|Coyote]]',
			$extraPages,
			'Coyote'
		];

		// link to redirect to content page without display title
		$extraPages = [
			'Elephant Page' => 'Content',
			'Redirect To Elephant Page' => '#REDIRECT [[Elephant Page]]'
		];

		yield [
			'Redirect to page without display title, no link text',
			'Test Page',
			'[[Redirect To Elephant Page]]',
			$extraPages,
			'Elephant Page'
		];

		yield [
			'Redirect to page without display title, lower case page title, no link text',
			'Test Page',
			'[[redirect To Elephant Page]]',
			$extraPages,
			'redirect To Elephant Page'
		];

		yield [
			'Redirect to page without display title, fragment, no link text',
			'Test Page',
			'[[Redirect To Elephant Page#Fragment]]',
			$extraPages,
			'Elephant Page'
		];

		yield [
			'Redirect to page without display title, page name link text',
			'Test Page',
			'[[Redirect To Elephant Page|Redirect To Elephant Page]]',
			$extraPages,
			'Elephant Page'
		];

		yield [
			'Redirect to page without display title, page name with underscores link text',
			'Test Page',
			'[[Redirect To Elephant Page|Redirect_To_Elephant_Page]]',
			$extraPages,
			'Elephant Page'
		];

		yield [
			'Redirect to page without display title, lowercase page name, page name link text',
			'Test Page',
			'[[redirect To Elephant Page|Redirect To Elephant Page]]',
			$extraPages,
			'Elephant Page'
		];

		yield [
			'Redirect to page without display title, lowercase page name, lowercase page name link text',
			'Test Page',
			'[[redirect To Elephant Page|redirect To Elephant Page]]',
			$extraPages,
			'redirect To Elephant Page'
		];

		yield [
			'Redirect to page without display title, lowercase page name link text',
			'Test Page',
			'[[Redirect To Elephant Page|redirect To Elephant Page]]',
			$extraPages,
			'redirect To Elephant Page'
		];

		yield [
			'Redirect to page without display title, other link text',
			'Test Page',
			'[[Redirect To Elephant Page|Coyote]]',
			$extraPages,
			'Coyote'
		];

		yield [
			'Redirect to page without display title, lowercase page name, other link text',
			'Test Page',
			'[[redirect To Elephant Page|Coyote]]',
			$extraPages,
			'Coyote'
		];

		// link to user page without display title
		$extraPages = [
			'User:Elephant Page' => 'Content'
		];

		yield [
			'Link to user page without display title, no link text',
			'Test Page',
			'[[User:Elephant Page]]',
			$extraPages,
			'User:Elephant Page'
		];

		yield [
			'Link to user page without display title, lower case page title, no link text',
			'Test Page',
			'[[User:elephant Page]]',
			$extraPages,
			'User:elephant Page'
		];

		yield [
			'Link to user page without display title, fragment, no link text',
			'Test Page',
			'[[User:Elephant Page#Fragment]]',
			$extraPages,
			'User:Elephant Page'
		];

		yield [
			'Link to user page without display title, page name link text',
			'Test Page',
			'[[User:Elephant Page|User:Elephant Page]]',
			$extraPages,
			'User:Elephant Page'
		];

		yield [
			'Link to user page without display title, page name with underscores link text',
			'Test Page',
			'[[User:Elephant Page|User:Elephant_Page]]',
			$extraPages,
			'User:Elephant_Page'
		];

		yield [
			'Link to user page without display title, lowercase page name, page name link text',
			'Test Page',
			'[[User:elephant Page|User:Elephant Page]]',
			$extraPages,
			'User:Elephant Page'
		];

		yield [
			'Link to user page without display title, lowercase page name, lowercase page name link text',
			'Test Page',
			'[[User:elephant Page|User:elephant Page]]',
			$extraPages,
			'User:elephant Page'
		];

		yield [
			'Link to user page without display title, lowercase page name link text',
			'Test Page',
			'[[User:Elephant Page|User:elephant Page]]',
			$extraPages,
			'User:elephant Page'
		];

		yield [
			'Link to user page without display title, other link text',
			'Test Page',
			'[[User:Elephant Page|Coyote]]',
			$extraPages,
			'Coyote'
		];

		yield [
			'Link to user page without display title, lowercase page name, other link text',
			'Test Page',
			'[[User:elephant Page|Coyote]]',
			$extraPages,
			'Coyote'
		];

		// link to content page with display title
		$extraPages = [
			'Dingo Page' => '{{DISPLAYTITLE:Zebra}}'
		];

		yield [
			'Link to page with display title, no link text',
			'Test Page',
			'[[Dingo Page]]',
			$extraPages,
			'Zebra'
		];

		yield [
			'Link to page with display title, lower case page title, no link text',
			'Test Page',
			'[[dingo Page]]',
			$extraPages,
			'dingo Page'
		];

		yield [
			'Link to page with display title, fragment, no link text',
			'Test Page',
			'[[Dingo Page#Fragment]]',
			$extraPages,
			'Zebra'
		];

		yield [
			'Link to page with display title, page name link text',
			'Test Page',
			'[[Dingo Page|Dingo Page]]',
			$extraPages,
			'Zebra'
		];

		yield [
			'Link to page with display title, page name with underscores link text',
			'Test Page',
			'[[Dingo Page|Dingo_Page]]',
			$extraPages,
			'Zebra'
		];

		yield [
			'Link to page with display title, lowercase page name, page name link text',
			'Test Page',
			'[[dingo Page|Dingo Page]]',
			$extraPages,
			'Zebra'
		];

		yield [
			'Link to page with display title, lowercase page name, lowercase page name link text',
			'Test Page',
			'[[dingo Page|dingo Page]]',
			$extraPages,
			'dingo Page'
		];

		yield [
			'Link to page with display title, lowercase page name link text',
			'Test Page',
			'[[Dingo Page|dingo Page]]',
			$extraPages,
			'dingo Page'
		];

		yield [
			'Link to page with display title, other link text',
			'Test Page',
			'[[Dingo Page|Coyote]]',
			$extraPages,
			'Coyote'
		];

		yield [
			'Link to page with display title, lowercase page name, other link text',
			'Test Page',
			'[[dingo Page|Coyote]]',
			$extraPages,
			'Coyote'
		];

		// link to redirect to content page with display title
		$extraPages = [
			'Dingo Page' => '{{DISPLAYTITLE:Zebra}}',
			'Redirect To Dingo Page' => '#REDIRECT [[Dingo Page]]'
		];

		yield [
			'Redirect to page with display title, no link text',
			'Test Page',
			'[[Redirect To Dingo Page]]',
			$extraPages,
			'Zebra'
		];

		yield [
			'Redirect to page with display title, lower case page title, no link text',
			'Test Page',
			'[[redirect To Dingo Page]]',
			$extraPages,
			'redirect To Dingo Page'
		];

		yield [
			'Redirect to page with display title, fragment, no link text',
			'Test Page',
			'[[Redirect To Dingo Page#Fragment]]',
			$extraPages,
			'Zebra'
		];

		yield [
			'Redirect to page with display title, page name link text',
			'Test Page',
			'[[Redirect To Dingo Page|Redirect To Dingo Page]]',
			$extraPages,
			'Zebra'
		];

		yield [
			'Redirect to page with display title, page name with underscores link text',
			'Test Page',
			'[[Redirect To Dingo Page|Redirect_To_Dingo_Page]]',
			$extraPages,
			'Zebra'
		];

		yield [
			'Redirect to page with display title, lowercase page name, page name link text',
			'Test Page',
			'[[redirect To Dingo Page|Redirect To Dingo Page]]',
			$extraPages,
			'Zebra'
		];

		yield [
			'Redirect to page with display title, lowercase page name, lowercase page name link text',
			'Test Page',
			'[[redirect To Dingo Page|redirect To Dingo Page]]',
			$extraPages,
			'redirect To Dingo Page'
		];

		yield [
			'Redirect to page with display title, lowercase page name link text',
			'Test Page',
			'[[Redirect To Dingo Page|redirect To Dingo Page]]',
			$extraPages,
			'redirect To Dingo Page'
		];

		yield [
			'Redirect to page with display title, other link text',
			'Test Page',
			'[[Redirect To Dingo Page|Coyote]]',
			$extraPages,
			'Coyote'
		];

		yield [
			'Redirect to page with display title, lowercase page name, other link text',
			'Test Page',
			'[[redirect To Dingo Page|Coyote]]',
			$extraPages,
			'Coyote'
		];

		// link to user page with display title
		$extraPages = [
			'User:Dingo Page' => '{{DISPLAYTITLE:Zebra}}'
		];

		yield [
			'Link to user page with display title, no link text',
			'Test Page',
			'[[User:Dingo Page]]',
			$extraPages,
			'Zebra'
		];

		yield [
			'Link to user page with display title, lower case page title, no link text',
			'Test Page',
			'[[User:dingo Page]]',
			$extraPages,
			'User:dingo Page'
		];

		yield [
			'Link to user page with display title, fragment, no link text',
			'Test Page',
			'[[User:Dingo Page#Fragment]]',
			$extraPages,
			'Zebra'
		];

		yield [
			'Link to user page with display title, page name link text',
			'Test Page',
			'[[User:Dingo Page|User:Dingo Page]]',
			$extraPages,
			'Zebra'
		];

		yield [
			'Link to user page with display title, page name with underscores link text',
			'Test Page',
			'[[User:Dingo Page|User:Dingo_Page]]',
			$extraPages,
			'Zebra'
		];

		yield [
			'Link to user page with display title, lowercase page name, page name link text',
			'Test Page',
			'[[User:dingo Page|User:Dingo Page]]',
			$extraPages,
			'Zebra'
		];

		yield [
			'Link to user page with display title, lowercase page name, lowercase page name link text',
			'Test Page',
			'[[User:dingo Page|User:dingo Page]]',
			$extraPages,
			'User:dingo Page'
		];

		yield [
			'Link to user page with display title, lowercase page name link text',
			'Test Page',
			'[[User:Dingo Page|User:dingo Page]]',
			$extraPages,
			'User:dingo Page'
		];

		yield [
			'Link to user page with display title, other link text',
			'Test Page',
			'[[User:Dingo Page|Coyote]]',
			$extraPages,
			'Coyote'
		];

		yield [
			'Link to user page with display title, lowercase page name, other link text',
			'Test Page',
			'[[User:dingo Page|Coyote]]',
			$extraPages,
			'Coyote'
		];
	}

	/**
	 * @dataProvider provideTestSelfLinks
	 */
	public function testSelfLinks( $testName, $titleText, $wikitext, $expectedLinkText ) {
		Title::clearCaches();
		$wikiPageFactory = $this->getServiceContainer()->getWikiPageFactory();

		$user = $this->getTestSysop()->getUser();
		$title = Title::newFromText( $titleText );
		$content = new WikitextContent( $wikitext );

		$page = $wikiPageFactory->newFromTitle( $title );
		$updater = $page->newPageUpdater( $user );
		$updater->setContent( 'main', $content );
		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment( 'new test page' ),
			EDIT_AUTOSUMMARY
		);

		$parserOptions = new ParserOptions( $user );
		$parserOptions->setRemoveComments( true );
		$contentRenderer = $this->getServiceContainer()->getContentRenderer();
		$parserOutput = $contentRenderer->getParserOutput( $content, $title->toPageIdentity(), null, $parserOptions );
		$actual = $parserOutput->getText();

		$this->assertStringContainsString( '>' . $expectedLinkText . '</a>', $actual, $testName );
	}

	public function provideTestSelfLinks() {
		// self link to content page without display title

		yield [
			'Self link to page without display title, no link text',
			'Snake Page',
			'[[Snake Page]]',
			'Snake Page'
		];

		yield [
			'Self link to page without display title, lower case page title, no link text',
			'Snake Page',
			'[[snake Page]]',
			'snake Page'
		];

		yield [
			'Self link to page without display title, fragment, no link text',
			'Snake Page',
			'[[Snake Page#Fragment]]',
			'Snake Page#Fragment'
		];

		yield [
			'Self link to page without display title, fragment only, no link text',
			'Snake Page',
			'[[#Fragment]]',
			'#Fragment'
		];

		yield [
			'Self link to page without display title, page name link text',
			'Snake Page',
			'[[Snake Page|Snake Page]]',
			'Snake Page'
		];

		yield [
			'Self link to page without display title, page name with underscores link text',
			'Snake Page',
			'[[Snake Page|Snake_Page]]',
			'Snake_Page'
		];

		yield [
			'Self link to page without display title, lowercase page name, page name link text',
			'Snake Page',
			'[[snake Page|Snake Page]]',
			'Snake Page'
		];

		yield [
			'Self link to page without display title, lowercase page name, lowercase page name link text',
			'Snake Page',
			'[[snake Page|snake Page]]',
			'snake Page'
		];

		yield [
			'Self link to page without display title, lowercase page name link text',
			'Snake Page',
			'[[Snake Page|snake Page]]',
			'snake Page'
		];

		yield [
			'Self link to page without display title, other link text',
			'Snake Page',
			'[[Snake Page|Coyote]]',
			'Coyote'
		];

		yield [
			'Self link to page without display title, lowercase page name, other link text',
			'Snake Page',
			'[[snake Page|Coyote]]',
			'Coyote'
		];

		// self link to content page with display title

		yield [
			'Self link to page with display title, no link text',
			'Sable Page',
			'{{DISPLAYTITLE:Zebra}}[[Sable Page]]',
			'Zebra'
		];

		yield [
			'Self link to page with display title, lower case page title, no link text',
			'Sable Page',
			'{{DISPLAYTITLE:Zebra}}[[sable Page]]',
			'sable Page'
		];

		yield [
			'Self link to page with display title, fragment, no link text',
			'Sable Page',
			'{{DISPLAYTITLE:Zebra}}[[Sable Page#Fragment]]',
			'Zebra'
		];

		yield [
			'Self link to page with display title, fragment only, no link text',
			'Sable Page',
			'{{DISPLAYTITLE:Zebra}}[[#Fragment]]',
			'#Fragment'
		];

		yield [
			'Self link to page with display title, page name link text',
			'Sable Page',
			'{{DISPLAYTITLE:Zebra}}[[Sable Page|Sable Page]]',
			'Zebra'
		];

		yield [
			'Self link to page with display title, page name with underscores link text',
			'Sable Page',
			'{{DISPLAYTITLE:Zebra}}[[Sable Page|Sable_Page]]',
			'Zebra'
		];

		yield [
			'Self link to page with display title, lowercase page name, page name link text',
			'Sable Page',
			'{{DISPLAYTITLE:Zebra}}[[sable Page|Sable Page]]',
			'Zebra'
		];

		yield [
			'Self link to page with display title, lowercase page name, lowercase page name link text',
			'Sable Page',
			'{{DISPLAYTITLE:Zebra}}[[sable Page|sable Page]]',
			'sable Page'
		];

		yield [
			'Self link to page with display title, lowercase page name link text',
			'Sable Page',
			'{{DISPLAYTITLE:Zebra}}[[Sable Page|sable Page]]',
			'sable Page'
		];

		yield [
			'Self link to page with display title, other link text',
			'Sable Page',
			'{{DISPLAYTITLE:Zebra}}[[Sable Page|Coyote]]',
			'Coyote'
		];

		yield [
			'Self link to page with display title, lowercase page name, other link text',
			'Sable Page',
			'{{DISPLAYTITLE:Zebra}}[[sable Page|Coyote]]',
			'Coyote'
		];
	}

	/**
	 * @dataProvider provideTestCategoryLinks
	 */
	public function testCategoryLinks( $testName, $titleText, $wikitext, $extraPages, $expectedLinkText ) {
		Title::clearCaches();
		$wikiPageFactory = $this->getServiceContainer()->getWikiPageFactory();

		$user = $this->getTestSysop()->getUser();
		foreach ( $extraPages as $extraTitle => $extraWikitext ) {
			$page = $wikiPageFactory->newFromTitle( Title::newFromText( $extraTitle ) );
			$updater = $page->newPageUpdater( $user );
			$updater->setContent( 'main', new WikitextContent( $extraWikitext ) );
			$updater->saveRevision(
				CommentStoreComment::newUnsavedComment( 'new test page' ),
				EDIT_AUTOSUMMARY
			);
		}

		$title = Title::newFromText( $titleText );
		$context = new RequestContext();
		$context->setTitle( $title );
		$context->setUser( $user );
		$output = $context->getOutput();
		$output->addWikiTextAsContent( $wikitext );
		$links = $output->getCategoryLinks();
		// there is only one link in these cases, but it's wrapped up in a 2d array
		$actual = array_values( array_values( $links )[0] )[0];

		$this->assertStringContainsString( '>' . $expectedLinkText . '</a>', $actual, $testName );
	}

	public function provideTestCategoryLinks() {
		// link to category page without display title
		$extraPages = [
			'Category:Elephant Category' => 'Content'
		];

		yield [
			'Link to category page without display title, no link text',
			'Test Page',
			'[[Category:Elephant Category]]',
			$extraPages,
			'Elephant Category'
		];

		yield [
			'Link to category page without display title, lower case page title, no link text',
			'Test Page',
			'[[Category:elephant Category]]',
			$extraPages,
			'Elephant Category'
		];

		// link to category page with display title
		$extraPages = [
			'Category:Dingo Category' => '{{DISPLAYTITLE:Zebra}}'
		];

		yield [
			'Link to category page with display title, no link text',
			'Test Page',
			'[[Category:Dingo Category]]',
			$extraPages,
			'Zebra'
		];

		yield [
			'Link to category page with display title, lower case page title, no link text',
			'Test Page',
			'[[Category:dingo Category]]',
			$extraPages,
			'Zebra'
		];
	}

	/**
	 * @dataProvider provideTestNoFollowRedirect
	 */
	public function testNoFollowRedirect( $testName, $wikitext ) {
		$this->overrideConfigValue( 'DisplayTitleFollowRedirects', false );
		Title::clearCaches();
		$wikiPageFactory = $this->getServiceContainer()->getWikiPageFactory();

		$extraPages = [
			'Dingo Page' => '{{DISPLAYTITLE:Zebra}}',
			'Redirect To Dingo Page' => '#REDIRECT [[Dingo Page]]'
		];

		$user = $this->getTestSysop()->getUser();
		foreach ( $extraPages as $extraTitle => $extraWikitext ) {
			$page = $wikiPageFactory->newFromTitle( Title::newFromText( $extraTitle ) );
			$updater = $page->newPageUpdater( $user );
			$updater->setContent( 'main', new WikitextContent( $extraWikitext ) );
			$updater->saveRevision(
				CommentStoreComment::newUnsavedComment( 'new test page' ),
				EDIT_AUTOSUMMARY
			);
		}

		$title = Title::newFromText( 'Test Page' );
		$content = new WikitextContent( $wikitext );
		$parserOptions = new ParserOptions( $user );
		$parserOptions->setRemoveComments( true );
		$contentRenderer = $this->getServiceContainer()->getContentRenderer();
		$parserOutput = $contentRenderer->getParserOutput( $content, $title->toPageIdentity(), null, $parserOptions );
		$actual = $parserOutput->getText();

		$this->assertStringNotContainsString( '>Zebra</a>', $actual, $testName );
	}

	public function provideTestNoFollowRedirect() {
		// link to redirect to content page with display title
		yield [
			'Redirect to page with display title, no link text',
			'[[Redirect To Dingo Page]]'
		];

		yield [
			'Redirect to page with display title, fragment, no link text',
			'[[Redirect To Dingo Page#Fragment]]'
		];

		yield [
			'Redirect to page with display title, page name link text',
			'[[Redirect To Dingo Page|Redirect To Dingo Page]]'
		];

		yield [
			'Redirect to page with display title, page name with underscores link text',
			'[[Redirect To Dingo Page|Redirect_To_Dingo_Page]]'
		];

		yield [
			'Redirect to page with display title, lowercase page name, page name link text',
			'[[redirect To Dingo Page|Redirect To Dingo Page]]'
		];
	}
}
