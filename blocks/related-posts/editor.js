(function() {
  window.wp.blocks.registerBlockType('momentive/related-posts', {
    edit: function() {
      return window.wp.element.createElement(
        'p',
        { style: { padding: '1rem', background: '#f0f0f0' } },
        'Related Posts (renders on front end)'
      );
    },
    save: function() { return null; }
  });
})();