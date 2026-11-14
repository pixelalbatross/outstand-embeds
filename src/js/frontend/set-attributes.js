/**
 * Set attributes on an element.
 *
 * @param {HTMLElement} element    The element.
 * @param {Object}      attributes The attribute names and values.
 */
export default function setAttributes( element, attributes ) {
	Object.entries( attributes ).forEach( ( [ name, value ] ) =>
		element.setAttribute( name, value )
	);
}
