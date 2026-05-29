/**
 * Millimeter / pixel conversion utilities (LightBurn-style coordinates).
 *
 * @package PCKZCanonicalEngine
 */
(function (global) {
	'use strict';

	const MM_PER_INCH = 25.4;

	/**
	 * @param {number} mm
	 * @param {number} dpi
	 * @returns {number}
	 */
	function mmToPx(mm, dpi) {
		return (mm / MM_PER_INCH) * dpi;
	}

	/**
	 * @param {number} px
	 * @param {number} dpi
	 * @returns {number}
	 */
	function pxToMm(px, dpi) {
		return (px / dpi) * MM_PER_INCH;
	}

	/**
	 * Convert screen Y to bottom-left origin Y (mm).
	 *
	 * @param {number} screenYmm
	 * @param {number} canvasHeightMm
	 * @param {number} objectHeightMm
	 * @param {string} origin
	 * @returns {number}
	 */
	function toOriginY(screenYmm, canvasHeightMm, objectHeightMm, origin) {
		if (origin === 'bottom-left') {
			return canvasHeightMm - screenYmm - objectHeightMm;
		}
		return screenYmm;
	}

	/**
	 * Convert bottom-left Y to screen Y (mm).
	 *
	 * @param {number} originYmm
	 * @param {number} canvasHeightMm
	 * @param {number} objectHeightMm
	 * @param {string} origin
	 * @returns {number}
	 */
	function fromOriginY(originYmm, canvasHeightMm, objectHeightMm, origin) {
		if (origin === 'bottom-left') {
			return canvasHeightMm - originYmm - objectHeightMm;
		}
		return originYmm;
	}

	/**
	 * Round mm value for display.
	 *
	 * @param {number} value
	 * @param {number} [decimals=2]
	 * @returns {number}
	 */
	function roundMm(value, decimals) {
		const d = decimals === undefined ? 2 : decimals;
		const factor = Math.pow(10, d);
		return Math.round(value * factor) / factor;
	}

	global.PCKZUnits = {
		MM_PER_INCH,
		mmToPx,
		pxToMm,
		toOriginY,
		fromOriginY,
		roundMm,
	};
})(typeof window !== 'undefined' ? window : this);
