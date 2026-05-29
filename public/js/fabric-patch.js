/**
 * Fabric.js compatibility patch (5.3.1 alphabetical → alphabetic).
 *
 * @package PCKZCanonicalEngine
 */
(function (global) {
	'use strict';

	if (typeof global.fabric === 'undefined' || !global.fabric.Text) {
		return;
	}

	const proto = global.fabric.Text.prototype;
	if (!proto || proto.__pckzceTextBaselinePatched) {
		return;
	}

	const original = proto._setTextStyles;
	if (typeof original !== 'function') {
		return;
	}

	proto._setTextStyles = function patchedSetTextStyles(ctx, charStyle, forMeasuring) {
		original.call(this, ctx, charStyle, forMeasuring);
		if (ctx && ctx.textBaseline === 'alphabetical') {
			ctx.textBaseline = 'alphabetic';
		}
	};
	proto.__pckzceTextBaselinePatched = true;
})(typeof window !== 'undefined' ? window : typeof globalThis !== 'undefined' ? globalThis : this);
