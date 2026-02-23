// VideoEmbed.jsx — Divi Builder React component for the Video Embed module.

import React, { Component } from 'react';

const { __ } = window.wp?.i18n ?? { __: ( s ) => s };

/**
 * Parse a YouTube or Vimeo URL and return { platform, id }.
 */
function parseVideoUrl( url = '' ) {
	url = url.trim();

	// YouTube: watch?v=
	let match = url.match( /(?:youtube\.com\/watch\?(?:[^&]*&)*v=)([a-zA-Z0-9_\-]{11})/ );
	if ( match ) return { platform: 'youtube', id: match[1] };

	// YouTube: youtu.be/
	match = url.match( /youtu\.be\/([a-zA-Z0-9_\-]{11})/ );
	if ( match ) return { platform: 'youtube', id: match[1] };

	// YouTube: embed/
	match = url.match( /youtube\.com\/embed\/([a-zA-Z0-9_\-]{11})/ );
	if ( match ) return { platform: 'youtube', id: match[1] };

	// Vimeo: player.vimeo.com/video/
	match = url.match( /player\.vimeo\.com\/video\/(\d+)/ );
	if ( match ) return { platform: 'vimeo', id: match[1] };

	// Vimeo: vimeo.com/ID
	match = url.match( /vimeo\.com\/(\d+)(?:[?\/]|$)/ );
	if ( match ) return { platform: 'vimeo', id: match[1] };

	return { platform: 'unknown', id: '' };
}

/**
 * Build the embed URL from platform + id.
 */
function buildEmbedUrl( platform, id ) {
	if ( 'youtube' === platform ) {
		return `https://www.youtube.com/embed/${ encodeURIComponent( id ) }?rel=0`;
	}
	if ( 'vimeo' === platform ) {
		return `https://player.vimeo.com/video/${ encodeURIComponent( id ) }`;
	}
	return '';
}

/**
 * Map ratio prop value to CSS class.
 */
function ratioClass( ratio ) {
	const map = {
		'16:9': 'dvp-ratio-16x9',
		'4:3':  'dvp-ratio-4x3',
		'1:1':  'dvp-ratio-1x1',
	};
	return map[ ratio ] ?? 'dvp-ratio-16x9';
}

class VideoEmbed extends Component {
	static slug = 'et_pb_video_embed';

	render() {
		const {
			video_mode       = 'dynamic',
			manual_video_url = '',
			video_ratio      = '16:9',
		} = this.props;

		const wrapperStyle = {
			position:   'relative',
			width:      '100%',
			overflow:   'hidden',
		};

		const iframeStyle = {
			position: 'absolute',
			top:      0,
			left:     0,
			width:    '100%',
			height:   '100%',
			border:   0,
		};

		// Dynamic mode — cannot resolve post meta inside the builder.
		if ( 'dynamic' === video_mode ) {
			return (
				<div className={ `dvp-video-wrapper ${ ratioClass( video_ratio ) } dvp-builder-placeholder` }
					style={ { ...wrapperStyle, minHeight: '180px', background: '#1a1a1a', display: 'flex', alignItems: 'center', justifyContent: 'center' } }>
					<p style={ { color: '#fff', textAlign: 'center', padding: '1em', margin: 0 } }>
						{ __( 'Video will load from post meta on the frontend.', 'divi-video-post' ) }
					</p>
				</div>
			);
		}

		// Manual mode — render a live preview if a valid URL is provided.
		if ( 'manual' === video_mode ) {
			const { platform, id } = parseVideoUrl( manual_video_url );
			const embedUrl = buildEmbedUrl( platform, id );

			if ( ! embedUrl ) {
				return (
					<div className={ `dvp-video-wrapper ${ ratioClass( video_ratio ) } dvp-builder-placeholder` }
						style={ { ...wrapperStyle, minHeight: '180px', background: '#1a1a1a', display: 'flex', alignItems: 'center', justifyContent: 'center' } }>
						<p style={ { color: '#aaa', textAlign: 'center', padding: '1em', margin: 0 } }>
							{ __( 'Enter a valid YouTube or Vimeo URL above to preview the video.', 'divi-video-post' ) }
						</p>
					</div>
				);
			}

			return (
				<div className={ `dvp-video-wrapper ${ ratioClass( video_ratio ) }` } style={ wrapperStyle }>
					<iframe
						src={ embedUrl }
						style={ iframeStyle }
						allowFullScreen
						loading="lazy"
						title={ __( 'Video embed preview', 'divi-video-post' ) }
					/>
				</div>
			);
		}

		return null;
	}
}

export default VideoEmbed;
