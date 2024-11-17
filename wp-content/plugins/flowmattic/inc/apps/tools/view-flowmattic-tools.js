/* global FlowMatticWorkflow, FlowMatticWorkflowEvents, FlowMatticWorkflowApp, FlowMatticWorkflowSteps */
var FlowMatticWorkflow = FlowMatticWorkflow || {};

( function( $ ) {

	jQuery( document ).ready( function() {
		// Workflow Trigger Tools View.
		FlowMatticWorkflow.ToolsView = Backbone.View.extend( {
			template: FlowMatticWorkflow.template( jQuery( '#flowmattic-application-tools-action-data-template' ).html() ),

			events: {
			},

			initialize: function() {
				// Unset the previous captured data.
				window.captureData = false;
			},

			render: function() {
				var thisEl = this,
					appAction = thisEl.model.get( 'action' ),
					actionTemplate;

				this.$el.html( this.template( this.model.toJSON() ) );

				if ( 'undefined' !== typeof appAction && '' !== appAction ) {
					actionTemplate = FlowMatticWorkflow.template( jQuery( '#flowmattic-tools-action-' + appAction + '-template' ).html() );

					jQuery( thisEl.$el ).find( '.tools-action-data' ).html( actionTemplate( thisEl.model.toJSON() ) );
				}

				thisEl.$el.find( 'select' ).selectpicker();

				return this;
			}
		} );
	} );
}( jQuery ) );
