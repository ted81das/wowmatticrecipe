/* global FlowMatticWorkflow, FlowMatticWorkflowEvents, FlowMatticWorkflowApp, FlowMatticWorkflowSteps */
var FlowMatticWorkflow = FlowMatticWorkflow || {};

( function( $ ) {

	jQuery( document ).ready( function() {
		// Workflow Trigger date time formatter View.
		FlowMatticWorkflow.Date_Time_FormatterView = Backbone.View.extend( {
			template: FlowMatticWorkflow.template( jQuery( '#flowmattic-application-date-time-formatter-action-data-template' ).html() ),

			events: {
			},

			initialize: function() {
				// Unset the previous captured data.
				window.captureData = false;
			},

			render: function() {
				var thisEl = this,
					appAction = this.model.get( 'action' ),
					actionTemplate;

				thisEl.$el.html( thisEl.template( thisEl.model.toJSON() ) );

				if ( jQuery( '#flowmattic-date-time-formatter-action-' + appAction + '-data-template' ).length ) {
					actionTemplate = FlowMatticWorkflow.template( jQuery( '#flowmattic-date-time-formatter-action-' + appAction + '-data-template' ).html() );
					jQuery( thisEl.$el ).find( '.date-time-formatter-action-data' ).html( actionTemplate( thisEl.model.toJSON() ) );
				}

				thisEl.$el.find( 'select' ).selectpicker();

				if ( this.$el.find( '.timezone-dropdown' ) ) {
					if ( 'undefined' !== typeof this.model.get( 'actionAppArgs' ) && 'undefined' !== typeof this.model.get( 'actionAppArgs' ).timezone ) {
						let timezone = this.model.get( 'actionAppArgs' ).timezone;
						thisEl.$el.find( '.timezone-dropdown' ).selectpicker( 'destroy' );
						thisEl.$el.find( '.timezone-dropdown' ).selectpicker( 'val', timezone );
					}
				}

				return this;
			}
		} );
	} );
}( jQuery ) );
