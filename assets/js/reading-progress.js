// reading-progress.js
( function () {
	var bar     = document.getElementById( 'reading-progress' );
	var content = document.querySelector( '.site-content' );
	if ( ! bar || ! content ) return;
	
	function update() {
		var rect    = content.getBoundingClientRect();
		var total   = content.offsetHeight - window.innerHeight;
		var scrolled = Math.max( 0, -rect.top );
		bar.style.setProperty( '--progress', Math.min( 1, scrolled / total ) );
	}
	
	document.addEventListener( 'scroll', update, { passive: true } );
	update();
} () );