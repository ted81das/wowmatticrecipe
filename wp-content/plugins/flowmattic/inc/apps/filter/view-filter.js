/* global FlowMatticWorkflow, FlowMatticWorkflowEvents, FlowMatticWorkflowApp, FlowMatticWorkflowSteps */
var FlowMatticWorkflow = FlowMatticWorkflow || {};

( function( $ ) {

	jQuery( document ).ready( function() {
		// Workflow Trigger Filter View.
		FlowMatticWorkflow.FilterView = Backbone.View.extend( {
			template: FlowMatticWorkflow.template( jQuery( '#flowmattic-application-filter-data-template' ).html() ),
			swalWithBootstrapButtons: window.Swal.mixin({
				customClass: {
					confirmButton: 'btn btn-primary shadow-none me-xxl-3',
					cancelButton: 'btn btn-danger shadow-none'
				},
				buttonsStyling: false
			} ),

			events: {
				'change select[name="filter-field-condition"]': 'checkFilterValueRequirement',
				'change .filter-condition-input': 'buildFilterConditionArray'
			},

			initialize: function() {
				// Unset the previous captured data.
				window.captureData = false;
			},

			render: function() {
				var thisEl = this,
					appAction = this.model.get( 'action' ),
					submissionData = {};

				if ( '' !== appAction ) {
					this.$el.html( this.template( this.model.toJSON() ) );
				}

				// Build the conditions.
				this.buildFilterConditions( this.model.toJSON() );

				if ( 'undefined' !== typeof this.model.get( 'capturedData' ) ) {
					capturedData = this.model.get( 'capturedData' );
					submissionData.capturedData = capturedData;
					submissionData.stepID = this.model.get( 'stepID' );

					FlowMatticWorkflowEvents.trigger( 'eventResponseReceived', submissionData, submissionData.stepID );
				}

				this.$el.find( 'select' ).selectpicker();

				return this;
			},

			toggleResponseData: function() {
				var toggleLink = jQuery( this.$el ).find( '.fm-response-data-toggle' ),
					toggleDataWrap = toggleLink.next( '.fm-response-body' );

				toggleLink.toggleClass( 'toggle' );
				toggleDataWrap.toggle( 'slideTop' );
			},

			buildFilterConditions: function( settings ) {
				var thisEl = this,
					conditionsTemplate = FlowMatticWorkflow.template( jQuery( '#flowmattic-filter-conditions-template' ).html() ),
					andOrButtonTemplate = FlowMatticWorkflow.template( jQuery( '#flowmattic-filter-and-or-template' ).html() ),
					andButtonTemplate = FlowMatticWorkflow.template( jQuery( '#flowmattic-filter-and-template' ).html() ),
					conditionsBody = jQuery( this.$el ).find( '.fm-filter-conditions-body' ); //.append( '<div class="fm-filter-condition"></div>' ),
					orConditionBlock = '<hr/><div class="fm-or-condition-badge justify-content-center mt-3 mb-3"><span class="badge bg-dark">OR</span></div>';

				if ( 'undefined' !== typeof settings.filterConditions ) {
					_.each ( settings.filterConditions, function( conditions, idx ) {
						_.each ( conditions, function( condition, index ) {
							var wrapper = conditionsBody.append( '<div class="fm-filter-condition"></div>' );
							wrapper.find( '.fm-filter-condition:empty' ).append( conditionsTemplate( condition ) );
						} );

						if ( ( parseInt( idx ) ) !== Object.keys( settings.filterConditions ).length ) {
							conditionsBody.append( andButtonTemplate() );
							conditionsBody.append( orConditionBlock );
						}
					} );

					conditionsBody.append( andOrButtonTemplate() );
				} else {
					conditionsBody.append( '<div class="fm-filter-condition"></div>' );
					conditionsBody.find( '.fm-filter-condition' ).append( conditionsTemplate() );
					conditionsBody.append( andOrButtonTemplate() );
				}

				this.$el.find( 'select' ).selectpicker();
			},

			checkFilterValueRequirement: function( event ) {
				var selectedFilterCondition = jQuery( event.target ).val();

				switch ( selectedFilterCondition ) {
					case 'is_true':
					case 'is_false':
					case 'exists':
					case 'does_not_exists':
						jQuery( event.target ).closest( '.fm-dynamic-input-wrap' ).find( '.filter-condition-value' ).css( 'opacity', '0' );
						break;

					default:
						jQuery( event.target ).closest( '.fm-dynamic-input-wrap' ).find( '.filter-condition-value' ).css( 'opacity', '1' );
						break;

				}
			},

			buildFilterConditionArray: function() {
				var thisEl = this,
					filterConditionBlocks = jQuery( this.$el ).find( '.fm-filter-condition' ),
					filterConditions = {},
					filterCondition = {},
					filterConditionIndex = 1;

				filterConditions[ filterConditionIndex ] = [];

				_.each ( filterConditionBlocks, function( field, index ) {
					var key = jQuery( field ).find( 'input[name="filter-field-key"]' ).val(),
						condition = jQuery( field ).find( 'select[name="filter-field-condition"]' ).val(),
						value = jQuery( field ).find( 'input[name="filter-field-value"]' ).val();

					if ( 'undefined' !== typeof key ) {
						filterCondition = {
							'key': key,
							'condition': condition,
							'value': value
						};

						if ( 1 === filterConditionBlocks.length ) {
							filterConditions[ filterConditionIndex ].push( filterCondition );
						} else {
							if ( jQuery( field ).next().is( '.fm-filter-condition' ) ) {
								filterConditions[ filterConditionIndex ].push( filterCondition );
							} else if ( jQuery( field ).next().is( '.fm-flowmattic-filter-add-more' ) && jQuery( field ).next().next().is( 'hr' ) ) {
								filterConditions[ filterConditionIndex ].push( filterCondition );

								filterConditionIndex++;

								if ( 'undefined' === typeof filterConditions[ filterConditionIndex ] ) {
									filterConditions[ filterConditionIndex ] = [];
								}
							} else {
								filterConditions[ filterConditionIndex ].push( filterCondition );
							}
						}

						thisEl.model.set( 'filterConditions', filterConditions );
						FlowMatticWorkflowEvents.trigger( 'actionAppDataUpdateSingleAttribute', 'filterConditions', filterConditions, thisEl );
					}
				} );
			}
		} );
	} );
}( jQuery ) );
