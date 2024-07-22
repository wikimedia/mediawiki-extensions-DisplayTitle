<?php
/*
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

namespace MediaWiki\Extension\DisplayTitle;

use HtmlArmor;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Page\RedirectLookup;
use MediaWiki\Page\WikiPageFactory;
use NamespaceInfo;
use OutputPage;
use PageProps;
use Title;

class DisplayTitleService {
	public const CONSTRUCTOR_OPTIONS = [
		'DisplayTitleHideSubtitle',
		'DisplayTitleExcludes',
		'DisplayTitleFollowRedirects'
	];

	/**
	 * @var bool
	 */
	private $hideSubtitle;

	/**
	 * @var array
	 */
	private $excludes;

	/**
	 * @var bool
	 */
	private $followRedirects;

	/**
	 * @var NamespaceInfo
	 */
	private $namespaceInfo;

	/**
	 * @var RedirectLookup
	 */
	private $redirectLookup;

	/**
	 * @var PageProps
	 */
	private $pageProps;

	/**
	 * @var WikiPageFactory
	 */
	private $wikiPageFactory;

	/**
	 * @param ServiceOptions $options
	 * @param NamespaceInfo $namespaceInfo
	 * @param RedirectLookup $redirectLookup
	 * @param PageProps $pageProps
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		ServiceOptions $options,
		NamespaceInfo $namespaceInfo,
		RedirectLookup $redirectLookup,
		PageProps $pageProps,
		WikiPageFactory $wikiPageFactory
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->hideSubtitle = $options->get( 'DisplayTitleHideSubtitle' );
		$this->excludes = $options->get( 'DisplayTitleExcludes' );
		$this->followRedirects = $options->get( 'DisplayTitleFollowRedirects' );
		$this->namespaceInfo = $namespaceInfo;
		$this->redirectLookup = $redirectLookup;
		$this->pageProps = $pageProps;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * Determines link text for self-links and standard links.
	 * If a link is customized by a user (e. g. [[Target|Text]])
	 * it should remain intact. Let us assume a link is not customized if its
	 * html is the prefixed or (to support Semantic MediaWiki queries)
	 * non-prefixed title of the target page.
	 *
	 * @since 1.3
	 * @param string $pageTitle
	 * @param Title $target the Title object that the link is pointing to
	 * @param string|HtmlArmor &$html the HTML of the link text
	 * @param bool $wrap whether to wrap result in HtmlArmor
	 */
	public function handleLink( string $pageTitle, Title $target, &$html, bool $wrap ) {
		// Do not use DisplayTitle if current page is defined in $wgDisplayTitleExcludes
		if ( in_array( $pageTitle, $this->excludes ) ) {
			return;
		}

		// Do not use DisplayTitle if the current page is a redirect to the page being linked
		$title = Title::newFromText( $pageTitle );
		if ( $title->canExist() ) {
			$wikipage = $this->wikiPageFactory->newFromTitle( $title );
			$redirectTarget = $this->redirectLookup->getRedirectTarget( $wikipage );
			if ( $redirectTarget && $pageTitle === $target->getPrefixedText() ) {
				return;
			}
		}

		$customized = false;
		if ( isset( $html ) ) {
			$text = null;
			if ( is_string( $html ) ) {
				$text = str_replace( '_', ' ', $html );
			} elseif ( is_int( $html ) ) {
				$text = (string)$html;
			} elseif ( $html instanceof HtmlArmor ) {
				$text = HtmlArmor::getHtml( $html );
				// Remove html tags used for highlighting matched words in the title, see T355481
				$text = strip_tags( $text );
				$text = str_replace( '_', ' ', $text );
			}

			// handle named Semantic MediaWiki subobjects (see T275984) by removing trailing fragment
			// skip fragment detection on category pages
			$fragment = '#' . $target->getFragment();
			if ( $text !== null && $fragment !== '#' && $target->getNamespace() !== NS_CATEGORY ) {
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
					$customized = $text !== $target->getPrefixedText() && $text !== $target->getText();
				}
			} else {
				$customized = $text !== null
					&& $text !== $target->getPrefixedText()
					&& $text !== $target->getSubpageText();
			}
		}
		if ( !$customized && $html !== null ) {
			$this->getDisplayTitle( $target, $html, $wrap );
		}
	}

	/**
	 * Get displaytitle page property text.
	 *
	 * @since 1.0
	 * @param Title $title the Title object for the page
	 * @param string|HtmlArmor &$displaytitle to return the display title, if set
	 * @param bool $wrap whether to wrap result in HtmlArmor
	 * @return bool true if the page has a displaytitle page property that is
	 * different from the prefixed page name, false otherwise
	 */
	public function getDisplayTitle( Title $title, &$displaytitle, bool $wrap = false ): bool {
		$title = $title->createFragmentTarget( '' );

		if ( !$title->canExist() ) {
			// If the Title isn't a valid content page (e.g. Special:UserLogin), just return.
			return false;
		}

		$originalPageName = $title->getPrefixedText();
		$wikipage = $this->wikiPageFactory->newFromTitle( $title );
		$redirect = false;
		if ( $this->followRedirects ) {
			$redirectTarget = $this->redirectLookup->getRedirectTarget( $wikipage );
			if ( $redirectTarget !== null ) {
				$redirect = true;
				$title = Title::newFromLinkTarget( $redirectTarget );
			}
		}
		$id = $title->getArticleID();
		$values = $this->pageProps->getProperties( $title, 'displaytitle' );
		if ( array_key_exists( $id, $values ) ) {
			$value = $values[$id];
			if ( trim( str_replace( '&#160;', '', strip_tags( $value ) ) ) !== '' &&
				$value !== $originalPageName ) {
				$displaytitle = $value;
				if ( $wrap ) {
					// @phan-suppress-next-line SecurityCheck-XSS
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

	/**
	 * Display subtitle if requested
	 *
	 * @since 4.0
	 * @param OutputPage $out
	 * @return void
	 */
	public function setSubtitle( OutputPage $out ): void {
		if ( $this->hideSubtitle ) {
			return;
		}
		$title = $out->getTitle();
		if ( !$title->isTalkPage() ) {
			$found = $this->getDisplayTitle( $title, $displaytitle );
		} else {
			$subjectPage = Title::castFromLinkTarget( $this->namespaceInfo->getSubjectPage( $title ) );
			if ( $subjectPage->exists() ) {
				$found = $this->getDisplayTitle( $subjectPage, $displaytitle );
			} else {
				$found = false;
			}
		}
		if ( $found ) {
			$out->addSubtitle( "<span class=\"mw-displaytitle-subtitle\">" . $title->getPrefixedText() . "</span>" );
		}
	}
}
