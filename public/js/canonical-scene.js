/**
 * Canonical scene JSON builder — single coordinate system (lightburn-mm-bottom-left).
 * Frontend responsibility: preview is rendered separately; this module only serializes geometry.
 *
 * @package PCKZCanonicalEngine
 */
(function (global) {
	'use strict';

	const COORD_SYSTEM = 'lightburn-mm-bottom-left';
	const FORMAT = 'pckzce-canonical-scene';
	const VERSION = 2;

	const Z_ORDER = {
		lines: 10,
		'line-overlay': 10,
		'strip-lines': 10,
		'icon-bg-left': 18,
		'icon-bg-right': 19,
		'icon-left': 20,
		'icon-right': 21,
		text: 30,
		'main-text': 30,
		logo: 25,
		image: 25,
	};

	function roundMm(value) {
		return Math.round(Number(value) * 1000) / 1000;
	}

	function roleToId(role, index) {
		const slug = String(role || 'object')
			.toLowerCase()
			.replace(/[^a-z0-9_-]+/g, '-')
			.replace(/^-+|-+$/g, '');
		return 'pckz-' + (slug || 'object') + (index > 0 ? '-' + index : '');
	}

	function normalizeBbox(mm) {
		if (!mm) {
			return null;
		}
		const x = roundMm(mm.x_mm);
		const y = roundMm(mm.y_mm);
		const width = roundMm(mm.width_mm);
		const height = roundMm(mm.height_mm);
		if (!Number.isFinite(x) || !Number.isFinite(y) || width <= 0 || height <= 0) {
			return null;
		}
		return {
			x_mm: x,
			y_mm: y,
			width_mm: width,
			height_mm: height,
			center_x_mm: roundMm(x + width / 2),
			center_y_mm: roundMm(y + height / 2),
		};
	}

	function resolveSvgRef(obj, selections, resolver) {
		if (typeof resolver === 'function') {
			return resolver(obj, selections) || obj.svg_url || obj.svg_ref || '';
		}
		return obj.svg_url || obj.svg_ref || obj.src || '';
	}

	/**
	 * Build canonical scene JSON from a production layout block.
	 *
	 * @param {object} layout Layout from preview engine.
	 * @param {object} meta   Extra metadata (product_id, preview_mode, resolver).
	 * @returns {object}
	 */
	function buildFromLayout(layout, meta) {
		meta = meta || {};
		if (!layout || !Array.isArray(layout.objects)) {
			return {
				format: FORMAT,
				version: VERSION,
				coordinate_system: COORD_SYSTEM,
				status: 'FAIL',
				errors: [{ code: 'missing_layout', message: 'Layout objects are required.' }],
				plate: { width_mm: 0, height_mm: 0 },
				objects: [],
			};
		}

		const plateW = roundMm(layout.canvas_mm?.width || meta.plate_width_mm || 0);
		const plateH = roundMm(layout.canvas_mm?.height || meta.plate_height_mm || 0);
		const selections = layout.selections || meta.selections || {};
		const roleCounts = {};
		const objects = [];
		const errors = [];

		layout.objects.forEach((obj) => {
			const role = obj.role || 'object';
			const count = roleCounts[role] || 0;
			roleCounts[role] = count + 1;
			const bbox = normalizeBbox(obj.mm || obj);
			if (!bbox) {
				errors.push({
					code: 'canonical_geometry_invalid',
					object_id: roleToId(role, count),
					role: role,
					message: 'Object bbox is invalid or missing in lightburn-mm-bottom-left.',
				});
				return;
			}

			const scaleX = roundMm(obj.scale?.x ?? obj.scaleX ?? 1);
			const scaleY = roundMm(obj.scale?.y ?? obj.scaleY ?? 1);
			const rotation = roundMm(obj.rotation_deg ?? obj.angle ?? 0);
			const svgRef = resolveSvgRef(obj, selections, meta.resolveAssetUrl);

			const entry = {
				id: roleToId(role, count),
				role: role,
				x_mm: bbox.x_mm,
				y_mm: bbox.y_mm,
				bbox: bbox,
				width_mm: bbox.width_mm,
				height_mm: bbox.height_mm,
				scale: { x: scaleX, y: scaleY },
				rotation_deg: rotation,
				z_order: Z_ORDER[role] || 15,
				color: obj.fill || obj.color || '',
				transforms: Array.isArray(obj.transforms) ? obj.transforms : [],
				fabric_geometry: obj.fabric_geometry || null,
				fabric: obj.fabric || null,
				svg_ref: svgRef || null,
				text: null,
				font_family: null,
				text_path_geometry: obj.text_path_geometry || obj.text_plate_paths || null,
			};

			if (role === 'text' || role === 'main-text') {
				entry.text = String(obj.text || '').trim();
				entry.font_family = obj.font_family || obj.fontFamily || '';
				entry.color = obj.fill || obj.color || selections.text_color || '';
				if (!entry.text) {
					errors.push({
						code: 'canonical_geometry_invalid',
						object_id: entry.id,
						role: role,
						message: 'Text object has empty value.',
					});
				}
			}

			if (role === 'lines' || role === 'line-overlay') {
				entry.line_type = obj.line_type || selections.linien || 'none';
				entry.color = obj.fill || obj.color || selections.line_color || '';
			}

			if (role === 'icon-left' || role === 'icon-right') {
				entry.symbol = obj.symbol || '';
			}

			objects.push(entry);
		});

		objects.sort((a, b) => a.z_order - b.z_order);

		const pipeline = global.PCKZCEProductionPipeline;
		let client_parity = null;
		if (pipeline && pipeline.ParityValidator && objects.length) {
			const pv = new pipeline.ParityValidator();
			client_parity = pv.validateCanonicalVsLayout(
				{ objects: objects.map((o) => ({ role: o.role, bbox: o.bbox, id: o.id })) },
				layout
			);
			if (client_parity.status === 'FAIL') {
				client_parity.errors.forEach((e) => errors.push(e));
			}
		}

		return {
			format: FORMAT,
			version: VERSION,
			coordinate_system: COORD_SYSTEM,
			engine: layout.engine || 'cloudlift-3651',
			export_pipeline: layout.export_pipeline || 'fabric-production-pipeline-v1',
			standard: layout.standard || '',
			generated_at: new Date().toISOString(),
			plate: {
				width_mm: plateW,
				height_mm: plateH,
			},
			safe_zone_mm: layout.safe_zone_mm || null,
			strip_zone_mm: layout.strip_zone_mm || null,
			design_px: layout.design_px || null,
			selections: selections,
			preview_mode: meta.preview_mode || layout.preview_mode || 'day',
			product_id: meta.product_id || 0,
			objects: objects,
			status: errors.length ? 'FAIL' : 'PASS',
			errors: errors,
			client_parity: client_parity,
		};
	}

	global.PCKZCECanonicalScene = {
		FORMAT: FORMAT,
		VERSION: VERSION,
		COORD_SYSTEM: COORD_SYSTEM,
		buildFromLayout: buildFromLayout,
		normalizeBbox: normalizeBbox,
	};
})(typeof window !== 'undefined' ? window : typeof globalThis !== 'undefined' ? globalThis : this);
