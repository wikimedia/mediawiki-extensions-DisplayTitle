<?php

namespace MediaWiki\Extension\DisplayTitle;

use MediaWiki\Extension\Scribunto\Hooks\ScribuntoExternalLibrariesHook;

class ScribuntoHooks implements
	ScribuntoExternalLibrariesHook
{
	/**
	 * Implements ScribuntoExternalLibraries hook.
	 * See https://www.mediawiki.org/wiki/Extension:Scribunto#Other_pages
	 * Handle Scribunto integration
	 *
	 * @since 1.2
	 * @param string $engine engine in use
	 * @param array &$extraLibraries list of registered libraries
	 */
	public function onScribuntoExternalLibraries( string $engine, array &$extraLibraries ) {
		if ( $engine === 'lua' ) {
			$extraLibraries['mw.ext.displaytitle'] = DisplayTitleLuaLibrary::class;
		}
	}
}
