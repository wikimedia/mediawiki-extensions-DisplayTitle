<?php

namespace MediaWiki\Extension\DisplayTitle;

use Config;
use HtmlArmor;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\SelfLinkBeginHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererBeginHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use OutputPage;
use Parser;
use ParserOutput;
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
	 * @var Config
	 */
	private $config;

	/**
	 * @param Config $config
	 */
	public function __construct(
		Config $config
	) {
		$this->config = $config;
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
	public function getdisplaytitleParserFunction( Parser $parser, $pagename ) {
		$title = Title::newFromText( $pagename );
		if ( $title !== null ) {
			self::getDisplayTitle( $title, $pagename );
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
	 * @param SkinTemplate $skin SkinTemplate object providing context
	 * @param array &$links The array of arrays of URLs set up so far
	 */
	public function onSkinTemplateNavigation__Universal( $skin, &$links ): void {
		if ( $skin->getUser()->isRegistered() ) {
			$menu_urls = $links['user-menu'] ?? [];
			if ( isset( $menu_urls['userpage'] ) ) {
				$pagename = $menu_urls['userpage']['text'];
				$title = $skin->getUser()->getUserPage();
				self::getDisplayTitle( $title, $pagename );
				$links['user-menu']['userpage']['text'] = $pagename;
			}
			$page_urls = $links['user-page'] ?? [];
			if ( isset( $page_urls['userpage'] ) ) {
				// If we determined $pagename already, don't do so again.
				if ( !isset( $menu_urls['userpage'] ) ) {
					$pagename = $page_urls['userpage']['text'];
					$title = $skin->getUser()->getUserPage();
					self::getDisplayTitle( $title, $pagename );
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
	 * @param string|HtmlArmor &$text the contents that the <a> tag should have
	 * @param array &$extraAttribs the HTML attributes that the <a> tag should have
	 * @param string &$query the query string to add to the generated URL
	 * @param string &$ret the value to return if the hook returns false
	 */
	public function onHtmlPageLinkRendererBegin( $linkRenderer, $target, &$text, &$extraAttribs, &$query, &$ret ) {
		// Do not use DisplayTitle if current page is defined in $wgDisplayTitleExcludes
		$request = $this->config->get( 'Request' );
		$title = $request->getVal( 'title' );
		if ( in_array( $title, $GLOBALS['wgDisplayTitleExcludes'] ) ) {
			return;
		}

		$title = Title::newFromLinkTarget( $target );
		self::handleLink( $title, $text, true );
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
		// Do not use DisplayTitle if current page is defined in $wgDisplayTitleExcludes
		$request = $this->config->get( 'Request' );
		$title = $request->getVal( 'title' );
		if ( in_array( $title, $GLOBALS['wgDisplayTitleExcludes'] ) ) {
			return;
		}

		self::handleLink( $nt, $html, false );
	}

	/**
	 * Helper function. Determines link text for self-links and standard links.
	 *
	 * Handle links. If a link is customized by a user (e. g. [[Target|Text]])
	 * it should remain intact. Let us assume a link is not customized if its
	 * html is the prefixed or (to support Semantic MediaWiki queries)
	 * non-prefixed title of the target page.
	 *
	 * @since 1.3
	 * @param Title $target the Title object that the link is pointing to
	 * @param string|HtmlArmor &$html the HTML of the link text
	 * @param bool $wrap whether to wrap result in HtmlArmor
	 */
	private static function handleLink( Title $target, &$html, $wrap ) {
		$customized = false;
		if ( isset( $html ) ) {
			$text = null;
			if ( is_string( $html ) ) {
				$text = str_replace( '_', ' ', $html );
			} elseif ( is_int( $html ) ) {
				$text = (string)$html;
			} elseif ( $html instanceof HtmlArmor ) {
				$text = str_replace( '_', ' ', HtmlArmor::getHtml( $html ) );
			}

			// handle named Semantic MediaWiki subobjects (see T275984) by removing trailing fragment
			// skip fragment detection on category pages
			$fragment = '#' . $target->getFragment();
			if ( $fragment !== '#' && $target->getNamespace() != NS_CATEGORY ) {
				$fragmentLength = strlen( $fragment );
				if ( substr( $text, -$fragmentLength ) === $fragment ) {
					// Remove fragment text from the link text
					$textTitle = substr( $text, 0, -$fragmentLength );
					$textFragment = substr( $fragment, 1 );
				} else {
					$textTitle = $text;
					$textFragment = '';
				}
				if ( $textTitle === '' || $textFragment === '' ) {
					$customized = true;
				} else {
					$text = $textTitle;
					if ( $wrap ) {
						$html = new HtmlArmor( $text );
					}
					$customized = $text != $target->getPrefixedText() && $text != $target->getText();
				}
			} else {
				$customized = $text !== null
					&& $text != $target->getPrefixedText()
					&& $text != $target->getText();
			}
		}
		if ( !$customized ) {
			self::getDisplayTitle( $target, $html, $wrap );
		}
	}

	/**
	 * Implements BeforePageDisplay hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * Display subtitle if requested
	 *
	 * @since 1.0
	 * @param OutputPage $out the OutputPage object
	 * @param Skin $sk the Skin object
	 */
	public function onBeforePageDisplay( $out, $sk ): void {
		if ( $GLOBALS['wgDisplayTitleHideSubtitle'] ) {
			return;
		}
		$title = $out->getTitle();
		if ( !$title->isTalkPage() ) {
			$found = self::getDisplayTitle( $title, $displaytitle );
		} elseif ( $title->getSubjectPage()->exists() ) {
			$found = self::getDisplayTitle( $title->getSubjectPage(), $displaytitle );
		} else {
			$found = false;
		}
		if ( $found ) {
			$subtitle = $title->getPrefixedText();
			$old_subtitle = $out->getSubtitle();
			if ( $old_subtitle !== '' ) {
				$subtitle .= ' / ' . $old_subtitle;
			}
			$out->setSubtitle( $subtitle );
		}
	}

	/**
	 * Implements OutputPageParserOutput hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/OutputPageParserOutput
	 * Handle talk page title.
	 *
	 * @param OutputPage $outputPage
	 * @param ParserOutput $parserOutput
	 * @since 1.0
	 */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		$title = $outputPage->getTitle();
		if ( $title !== null && $title->isTalkPage() && $title->getSubjectPage()->exists() ) {
			$found = self::getDisplayTitle( $title->getSubjectPage(), $displaytitle );
			if ( $found ) {
				$displaytitle = wfMessage( 'displaytitle-talkpagetitle',
					$displaytitle )->plain();
				$parserOutput->setTitleText( $displaytitle );
			}
		}
	}

	/**
	 * Implements ScribuntoExternalLibraries hook.
	 * See https://www.mediawiki.org/wiki/Extension:Scribunto#Other_pages
	 * Handle Scribunto integration
	 *
	 * @since 1.2
	 * @param string $engine engine in use
	 * @param array &$extraLibraries list of registered libraries
	 */
	public static function onScribuntoExternalLibraries( $engine, array &$extraLibraries ) {
		if ( $engine === 'lua' ) {
			$extraLibraries['mw.ext.displaytitle'] = 'DisplayTitleLuaLibrary';
		}
	}

	/**
	 * Get displaytitle page property text.
	 *
	 * @since 1.0
	 * @param Title $title the Title object for the page
	 * @param string &$displaytitle to return the display title, if set
	 * @param bool $wrap whether to wrap result in HtmlArmor
	 * @return bool true if the page has a displaytitle page property that is
	 * different from the prefixed page name, false otherwise
	 */
	public static function getDisplayTitle( Title $title, &$displaytitle,
		$wrap = false ) {
		$title = $title->createFragmentTarget( '' );

		if ( !$title->canExist() ) {
			// If the Title isn't a valid content page (e.g. Special:UserLogin), just return.
			return false;
		}

		$originalPageName = $title->getPrefixedText();
		$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		$wikipage = $wikiPageFactory->newFromTitle( $title );
		$redirect = false;
		if ( $GLOBALS['wgDisplayTitleFollowRedirects'] ) {
			$redirectTarget = $wikipage->getRedirectTarget();
			if ( $redirectTarget !== null ) {
				$redirect = true;
				$title = $redirectTarget;
			}
		}
		$id = $title->getArticleID();
		$pageProps = MediaWikiServices::getInstance()->getPageProps();
		$values = $pageProps->getProperties( $title, 'displaytitle' );
		if ( array_key_exists( $id, $values ) ) {
			$value = $values[$id];
			if ( trim( str_replace( '&#160;', '', strip_tags( $value ) ) ) !== '' &&
				$value !== $originalPageName ) {
				$displaytitle = $value;
				if ( $wrap ) {
					$displaytitle = new HtmlArmor( $displaytitle );
				}
				return true;
			}
		} elseif ( $redirect ) {
			$displaytitle = $title->getPrefixedText();
			if ( $wrap ) {
				$displaytitle = new HtmlArmor( $displaytitle );
			}
			return true;
		}
		return false;
	}
}
