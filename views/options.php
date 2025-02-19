<div class="panel panel-default fpbx-usageinfo">
	<div class="panel-heading">
		<a data-toggle="collapse" data-target="#collapseOne">Options <small>(Click to Expand)</small></a>
	</div>
	<div id="collapseOne" class="panel-collapse collapse">
		<div class="panel-body">
			<div class="fpbx-container">
				<div class="display full-border">
					<form class="fpbx-submit" name="editCpviz" action="?display=cpviz&action=edit" method="post">
					<!--datetime-->
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="datetime"><?php echo _("Date & Time Stamp") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="datetime"></i>
										</div>
										<div class="col-md-9 radioset">
											<input type="radio" name="datetime" id="datetimeyes" value="1" <?php echo ($datetime?"CHECKED":"") ?>>
											<label for="datetimeyes"><?php echo _("Yes");?></label>
											<input type="radio" name="datetime" id="datetimeno" value="0" <?php echo ($datetime?"":"CHECKED") ?>>
											<label for="datetimeno"><?php echo _("No");?></label>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="datetime-help" class="help-block fpbx-help-block"><?php echo _("Display the date and time within graph.")?></span>
							</div>
						</div>
					</div>
					<!--END datetime-->	
					<!--horizontal-->
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="horizontal"><?php echo _("Horizontal Layout") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="horizontal"></i>
										</div>
										<div class="col-md-9 radioset">
											<input type="radio" name="horizontal" id="horizontalyes" value="1" <?php echo ($horizontal?"CHECKED":"") ?>>
											<label for="horizontalyes"><?php echo _("Yes");?></label>
											<input type="radio" name="horizontal" id="horizontalno" value="0" <?php echo ($horizontal?"":"CHECKED") ?>>
											<label for="horizontalno"><?php echo _("No");?></label>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="horizontal-help" class="help-block fpbx-help-block"><?php echo _("Display the dial plan horizontially.")?></span>
							</div>
						</div>
					</div>
					<!--END horizontal-->
					<!--panzoom-->
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="panzoom"><?php echo _("Pan & Zoom") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="panzoom"></i>
										</div>
										<div class="col-md-9 radioset">
											<input type="radio" name="panzoom" id="panzoomyes" value="1" <?php echo ($panzoom?"CHECKED":"") ?>>
											<label for="panzoomyes"><?php echo _("Yes");?></label>
											<input type="radio" name="panzoom" id="panzoomno" value="0" <?php echo ($panzoom?"":"CHECKED") ?>>
											<label for="panzoomno"><?php echo _("No");?></label>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="panzoom-help" class="help-block fpbx-help-block"><?php echo _("Enables pan and zoom functions.")?></span>
							</div>
						</div>
					</div>
					<!--END panzoom-->
					<!--destination-->
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="destination"><?php echo _("Show Destination Column") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="destination"></i>
										</div>
										<div class="col-md-9 radioset">
											<input type="radio" name="destination" id="destinationyes" value="1" <?php echo ($destinationColumn?"CHECKED":"") ?>>
											<label for="destinationyes"><?php echo _("Yes");?></label>
											<input type="radio" name="destination" id="destinationno" value="0" <?php echo ($destinationColumn?"":"CHECKED") ?>>
											<label for="destinationno"><?php echo _("No");?></label>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="destination-help" class="help-block fpbx-help-block"><?php echo _("Shows the destination column for each inbound route. May affect performance if there are a lot of inbound routes.")?></span>
							</div>
						</div>
					</div>
					<!--END destination-->					

					<div class="row">
						<div class="col-md-12 text-right">
							<input class="btn btn-primary" name="submit" type="submit" value="Submit" id="submit">
						</div>
					</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>