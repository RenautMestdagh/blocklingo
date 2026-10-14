/**
 * Settings → Blocklingo: add and remove language rows; the "default" radio follows the
 * code typed in its row.
 */
( function () {
	const table = document.querySelector( '.blocklingo-languages tbody' );
	const template = document.getElementById( 'blocklingo-row-template' );
	const add = document.querySelector( '.blocklingo-add' );
	if ( ! table || ! template || ! add ) {
		return;
	}

	let next = table.querySelectorAll( 'tr' ).length;

	add.addEventListener( 'click', () => {
		const html = template.innerHTML.replace(
			/__INDEX__/g,
			String( next++ )
		);
		table.insertAdjacentHTML( 'beforeend', html );
		table.querySelector( 'tr:last-child input[type="text"]' )?.focus();
	} );

	table.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '.blocklingo-remove' );
		if ( ! button ) {
			return;
		}
		const row = button.closest( 'tr' );
		const isDefault = row.querySelector( '.blocklingo-default' )?.checked;
		if ( isDefault || table.querySelectorAll( 'tr' ).length < 2 ) {
			return;
		}
		row.remove();
		add.focus();
	} );

	table.addEventListener( 'input', ( event ) => {
		if ( event.target.classList.contains( 'blocklingo-code' ) ) {
			const radio = event.target
				.closest( 'tr' )
				.querySelector( '.blocklingo-default' );
			if ( radio ) {
				radio.value = event.target.value.trim().toLowerCase();
			}
		}
	} );
} )();
