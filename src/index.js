/**
 * src/index.js — webpack entry point for the Divi Video Post extension.
 *
 * This file is compiled by webpack into build/divi-video-post.min.js, which
 * Divi's DiviExtension PHP class enqueues inside the Visual Builder context.
 *
 * It imports each module's React component and registers it with Divi so the
 * Visual Builder can render a live preview for the et_pb_video_embed module.
 *
 * How Divi module component registration works
 * --------------------------------------------
 * When the Visual Builder initialises it fires the custom event
 * `et_builder_api_ready` on `window`. At that point the global
 * `window.ETBuilderBackend` object and the React-based module registry are
 * available. We listen for that event and register each component by the
 * same slug that its corresponding PHP ET_Builder_Module class declares.
 *
 * If Divi ever changes the event name or registry API, only this file needs
 * to be updated — the module components themselves stay unchanged.
 */

import VideoEmbed from '../modules/VideoEmbed/VideoEmbed';

/**
 * Register a single module component with the Divi Visual Builder.
 *
 * @param {Function} Component - React component class with a static `slug` property.
 */
function registerModule( Component ) {
	if ( ! Component || ! Component.slug ) {
		return;
	}

	// Primary path: ET Builder module registry (Divi 4.x Visual Builder).
	if (
		window.ET_Builder &&
		window.ET_Builder.api &&
		window.ET_Builder.api.module &&
		typeof window.ET_Builder.api.module.registerModule === 'function'
	) {
		window.ET_Builder.api.module.registerModule( Component, null );
		return;
	}

	// Legacy / alternative path used by some Divi 4 releases.
	if (
		window.ETBuilderBackend &&
		typeof window.ETBuilderBackend.registerModuleComponent === 'function'
	) {
		window.ETBuilderBackend.registerModuleComponent( Component.slug, Component );
		return;
	}

	// Fallback: expose on a namespaced global so a later script or Divi
	// itself can pick up the component.
	window.DVPModules = window.DVPModules || {};
	window.DVPModules[ Component.slug ] = Component;
}

/**
 * Run registration once the Divi Builder API is ready.
 *
 * Divi dispatches `et_builder_api_ready` on `window` before rendering the
 * canvas; that is the earliest safe moment to call register functions.
 */
function onBuilderReady() {
	registerModule( VideoEmbed );
}

// The event may already have fired if this script loads late (unlikely in
// Divi's normal enqueue order, but handle it defensively).
if ( window.ET_Builder && window.ET_Builder.api ) {
	onBuilderReady();
} else {
	window.addEventListener( 'et_builder_api_ready', onBuilderReady, { once: true } );
}
