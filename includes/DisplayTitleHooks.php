<?php

namespace MediaWiki\Extension\DisplayTitle;

use HtmlArmor;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\SelfLinkBeginHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererBeginHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use NamespaceInfo;
use OutputPage;
use Parser;
use ParserOutput;
use RequestContext;
use Skin;
use SkinTemplate;
use Title;

class DisplayTitleHooks implements
	ParserFirstCallInitHook,
	BeforePageDisplayHook,
	HtmlPageLinkRendererBeginHook,
	OutputPageParserOutputHook,
	SelfLinkBeginHook,
	SkinTemplateNavigation__UniversalHook
{
	/**
	 * @var DisplayTitleService
	 */
	private $displayTitleService;

	/**
	 * @var NamespaceInfo
	 */
	private $namespaceInfo;

	/**
	 * @param DisplayTitleService $displayTitleService
	 * @param NamespaceInfo $namespaceInfo
	 */
	public function __construct(
		DisplayTitleService $displayTitleService,
		NamespaceInfo $namespaceInfo
	) {
		$this->displayTitleService = $displayTitleService;
		$this->namespaceInfo = $namespaceInfo;
	}

	/**
	 * Implements ParserFirstCallInit hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @since 1.0
	 * @param Parser $parser the Parser object
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook(
			'getdisplaytitle',
			[ $this, 'getdisplaytitleParserFunction' ]
		);
	}

	/**
	 * Handle #getdisplaytitle parser function.
	 *
	 * @since 1.0
	 * @param Parser $parser the Parser object
	 * @param string $pagename the name of the page
	 * @return string the displaytitle of the page; defaults to pagename if
	 * displaytitle is not set
	 */
	public function getdisplaytitleParserFunction( Parser $parser, string $pagename ): string {
		$title = Title::newFromText( $pagename );
		if ( $title !== null ) {
			$this->displayTitleService->getDisplayTitle( $title, $pagename );
		}
		return $pagename;
	}

	// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

	/**
	 * Implements SkinTemplateNavigation::Universal hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation::Universal
	 *
	 * Migrated from PersonalUrls hook, see https://phabricator.wikimedia.org/T319087
	 *
	 * @since 1.5
	 * @param SkinTemplate $sktemplate SkinTemplate object providing context
	 * @param array &$links The array of arrays of URLs set up so far
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		$pagename = null;
		if ( $sktemplate->getUser()->isRegistered() ) {
			$menu_urls = $links['user-menu'] ?? [];
			if ( isset( $menu_urls['userpage'] ) ) {
				$pagename = $menu_urls['userpage']['text'];
				$title = $sktemplate->getUser()->getUserPage();
				$this->displayTitleService->getDisplayTitle( $title, $pagename );
				$links['user-menu']['userpage']['text'] = $pagename;
			}
			$page_urls = $links['user-page'] ?? [];
			if ( isset( $page_urls['userpage'] ) ) {
				// If we determined $pagename already, don't do so again.
				if ( $pagename === null ) {
					$pagename = $page_urls['userpage']['text'];
					$title = $sktemplate->getUser()->getUserPage();
					$this->displayTitleService->getDisplayTitle( $title, $pagename );
				}
				$links['user-page']['userpage']['text'] = $pagename;
			}
		}
	}

	/**
	 * Implements HtmlPageLinkRendererBegin hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/HtmlPageLinkRendererBegin
	 * Handle links to other pages.
	 *
	 * @since 1.4
	 * @param LinkRenderer $linkRenderer the LinkRenderer object
	 * @param LinkTarget $target the LinkTarget that the link is pointing to
	 * @param string|null|HtmlArmor &$text the contents that the <a> tag should have
	 * @param string[] &$customAttribs the HTML attributes that the <a> tag should have
	 * @param string[] &$query the query string to add to the generated URL
	 * @param string &$ret the value to return if the hook returns false
	 */
	public function onHtmlPageLinkRendererBegin( $linkRenderer, $target, &$text, &$customAttribs, &$query, &$ret ) {
		$title = RequestContext::getMain()->getTitle();
		if ( $title ) {
			$this->displayTitleService->handleLink(
				$title->getPrefixedText(),
				Title::newFromLinkTarget( $target ),
				$text,
				true
			);
		}
	}

	/**
	 * Implements SelfLinkBegin hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/SelfLinkBegin
	 * Handle self links.
	 *
	 * @since 1.3
	 * @param Title $nt the Title object of the page
	 * @param string &$html the HTML of the link text
	 * @param string &$trail Text after link
	 * @param string &$prefix Text before link
	 * @param string &$ret the value to return if the hook returns false
	 */
	public function onSelfLinkBegin( $nt, &$html, &$trail, &$prefix, &$ret ) {
		$this->displayTitleService->handleLink( $nt->getPrefixedText(), $nt, $html, false );
	}

	/**
	 * Implements BeforePageDisplay hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * Display subtitle if requested
	 *
	 * @since 1.0
	 * @param OutputPage $out the OutputPage object
	 * @param Skin $skin the Skin object
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$this->displayTitleService->setSubtitle( $out );
	}

	/**
	 * Implements OutputPageParserOutput hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/OutputPageParserOutput
	 * Handle talk page title.
	 *
	 * @since 1.0
	 * @param OutputPage $outputPage
	 * @param ParserOutput $parserOutput
	 */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		$title = $outputPage->getTitle();
		if ( $title !== null && $title->isTalkPage() ) {
			$subjectPage = Title::castFromLinkTarget( $this->namespaceInfo->getSubjectPage( $title ) );
			if ( $subjectPage->exists() ) {
				$found = $this->displayTitleService->getDisplayTitle( $subjectPage, $displaytitle );
				if ( $found ) {
					$displaytitle = wfMessage( 'displaytitle-talkpagetitle',
						$displaytitle )->plain();
					$parserOutput->setTitleText( $displaytitle );
				}
			}
		}
	}
}
