$(window).load(function()
{
	$('.column').sortable({
		connectWith: '.column',
		handle: 'h2',
		cursor: 'move',
		placeholder: 'placeholder',
		forcePlaceholderSize: true,
		opacity: 0.4,
		stop: function(event, ui){
			updateWidgetData();
		}
	}).disableSelection();
	
	$('.dragbox').resizable(
	{
		handles: "s",
		minHeight: 150,
		resize: function( event, ui )
		{
			$(ui.element).find('.dragbox-content').height(ui.size.height - 30);
			$(ui.element).width('100%');
		},
		stop: function( event, ui )
		{
			updateWidgetData();
		}
	});
	
});

function updateWidgetData(){
	var items=[];
	$('.column').each(function(){
		var columnId=$(this).attr('id');
		$('.dragbox', this).each(function(i){
			var collapsed=0;
			if($(this).find('.dragbox-content').css('display')=="none")
				collapsed=1;
			var item={
				id: $(this).attr('id'),
				collapsed: collapsed,
				order : i,
				column: columnId,
				height: $(this).find('.dragbox-content').height()
			};
			items.push(item);
		});
	});
	var sortorder={ items: items };
			
	//Pass sortorder variable to server using ajax to save state
	$.post('update_dash.php', 'data='+$.toJSON(sortorder), function(response){
		/*if(response=="success")
			$("#console").html('<div class="success">Saved</div>').hide().fadeIn(1000);
		setTimeout(function(){
			$('#console').fadeOut(1000);
		}, 2000);*/
	});
}
