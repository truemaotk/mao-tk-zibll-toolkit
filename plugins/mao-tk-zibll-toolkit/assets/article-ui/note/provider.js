( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';
	// The server selects one provider; protect against an already registered client block too.
	if ( blocks.getBlockType( 'maotk/note' ) ) { return; }

	var registerBlockType = blocks.registerBlockType;
	var BlockControls = blockEditor.BlockControls;
	var InnerBlocks = blockEditor.InnerBlocks;
	var InspectorControls = blockEditor.InspectorControls;
	var RichText = blockEditor.RichText;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;
	var ToolbarDropdownMenu = components.ToolbarDropdownMenu;
	var createElement = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;

	var NOTE_TYPES = [
		{ label: __( '说明（橙色）', 'maotk-note-block' ), value: 'info', badge: __( '说明', 'maotk-note-block' ) },
		{ label: __( '提示（蓝色）', 'maotk-note-block' ), value: 'primary', badge: __( '提示', 'maotk-note-block' ) },
		{ label: __( '推荐（绿色）', 'maotk-note-block' ), value: 'success', badge: __( '推荐', 'maotk-note-block' ) },
		{ label: __( '注意（黄色）', 'maotk-note-block' ), value: 'warning', badge: __( '注意', 'maotk-note-block' ) },
		{ label: __( '风险（红色）', 'maotk-note-block' ), value: 'danger', badge: __( '风险', 'maotk-note-block' ) }
	];

	var ALLOWED_BLOCKS = [ 'core/paragraph', 'core/list' ];
	var TEMPLATE = [ [ 'core/paragraph', { placeholder: __( '输入提示框正文…', 'maotk-note-block' ) } ] ];

	function getType( value ) {
		for ( var index = 0; index < NOTE_TYPES.length; index++ ) {
			if ( NOTE_TYPES[ index ].value === value ) {
				return NOTE_TYPES[ index ];
			}
		}

		return NOTE_TYPES[ 0 ];
	}

	function isDefaultBadge( badge ) {
		for ( var index = 0; index < NOTE_TYPES.length; index++ ) {
			if ( NOTE_TYPES[ index ].badge === badge ) {
				return true;
			}
		}

		return ! badge;
	}

	function getWrapperClass( attributes ) {
		var classNames = [ 'maotk-note-clean', 'maotk-note-' + getType( attributes.type ).value ];

		if ( attributes.showBadge ) {
			classNames.push( 'has-maotk-note-badge' );
		}

		if ( attributes.showTitle ) {
			classNames.push( 'has-maotk-note-title' );
		}

		return classNames.join( ' ' );
	}

	registerBlockType( 'maotk/note', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var currentType = getType( attributes.type );
			var blockProps = useBlockProps( {
				className: getWrapperClass( attributes ),
				'data-maotk-note-type': currentType.value
			} );

			function changeType( nextType ) {
				var next = getType( nextType );
				var updates = { type: next.value };

				if ( isDefaultBadge( attributes.badge ) ) {
					updates.badge = next.badge;
				}

				setAttributes( updates );
			}

			var toolbarControls = NOTE_TYPES.map( function ( noteType ) {
				return {
					title: noteType.label,
					onClick: function () {
						changeType( noteType.value );
					},
					isActive: currentType.value === noteType.value
				};
			} );

			return createElement(
				Fragment,
				null,
				createElement(
					BlockControls,
					null,
					createElement( ToolbarDropdownMenu, {
						icon: 'info-outline',
						label: __( '切换提示类型', 'maotk-note-block' ),
						controls: toolbarControls
					} )
				),
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{ title: __( '提示框设置', 'maotk-note-block' ), initialOpen: true },
						createElement( SelectControl, {
							label: __( '提示类型', 'maotk-note-block' ),
							value: currentType.value,
							options: NOTE_TYPES,
							onChange: changeType,
							__nextHasNoMarginBottom: true
						} ),
						createElement( ToggleControl, {
							label: __( '显示左上角标签', 'maotk-note-block' ),
							checked: attributes.showBadge,
							onChange: function ( value ) {
								setAttributes( { showBadge: value } );
							},
							__nextHasNoMarginBottom: true
						} ),
						createElement( ToggleControl, {
							label: __( '显示标题', 'maotk-note-block' ),
							checked: attributes.showTitle,
							onChange: function ( value ) {
								setAttributes( { showTitle: value } );
							},
							__nextHasNoMarginBottom: true
						} )
					)
				),
				createElement(
					'aside',
					blockProps,
					attributes.showBadge && createElement(
						'div',
						{ className: 'maotk-note-badge' },
						createElement( RichText, {
							tagName: 'span',
							value: attributes.badge,
							onChange: function ( value ) {
								setAttributes( { badge: value } );
							},
							placeholder: currentType.badge,
							allowedFormats: []
						} )
					),
					attributes.showTitle && createElement( RichText, {
						tagName: 'div',
						className: 'maotk-note-title',
						value: attributes.title,
						onChange: function ( value ) {
							setAttributes( { title: value } );
						},
						placeholder: __( '输入提示框标题…', 'maotk-note-block' ),
						allowedFormats: []
					} ),
					createElement(
						'div',
						{ className: 'maotk-note-content' },
						createElement( InnerBlocks, {
							allowedBlocks: ALLOWED_BLOCKS,
							template: TEMPLATE,
							templateLock: false,
							renderAppender: InnerBlocks.ButtonBlockAppender
						} )
					)
				)
			);
		},

		save: function ( props ) {
			var attributes = props.attributes;
			var currentType = getType( attributes.type );
			var blockProps = useBlockProps.save( {
				className: getWrapperClass( attributes ),
				'data-maotk-note-type': currentType.value,
				'aria-label': attributes.showBadge && attributes.badge ? attributes.badge : '提示'
			} );

			return createElement(
				'aside',
				blockProps,
				attributes.showBadge && attributes.badge && createElement(
					'div',
					{ className: 'maotk-note-badge' },
					createElement( RichText.Content, {
						tagName: 'span',
						value: attributes.badge
					} )
				),
				attributes.showTitle && attributes.title && createElement( RichText.Content, {
					tagName: 'div',
					className: 'maotk-note-title',
					value: attributes.title
				} ),
				createElement(
					'div',
					{ className: 'maotk-note-content' },
					createElement( InnerBlocks.Content )
				)
			);
		}
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
