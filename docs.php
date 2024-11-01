<?php
class time_sheets_docs {
	function main() {
		echo "<br>
			Welcome to the documentation for the Timesheet plugin.  There's a lot of information contained in here, but it's a rather large complex application so hopefully, you'll forgive the lengthy documentation.<P>

			In order to get started the first thing you need to do is grant a user the permissions to manage clients.  Even if you are an admin on the Wordpress site you still need to have this permission added.  Do this under the 'Manage Client Managers' menu.  If you are the person who's going to be adding new clients into the system add yourself.  If someone else will be doing this, add them.  You give people this permission by clicking the green check box in the 'Add Permission' column.<P>

			Next, you'll need to setup an approval team. This is the person that can approve the timesheets of the people that work for them. Normally this would be the supervisors with their team members under them.  Some will setup managers with the supervisors reporting to them.  This requires going through two menus.  First, you need to go into the 'Manage Approvers' menu and mark who will be an approver.  Then assign their team members to them in the 'Setup Approval Teams' menu.<P>

			
			That's the basics.  If you plan on using the Invoicing and/or Payroll workflows you'll need to give people access to those menus using the 'Manage Invoices' and 'Manage Payroll' menus respectively.  Again if you are an admin you won't be able to see these functions without giving yourself permissions to this.

			You'll also want to go into the 'Global Settings' as there are a LOT of features that you can turn on and off as needed to meet your needs.  Most of these are self-explanatory, those that aren't are spelled out below.  Anything that's required should auto-populate with a default setting.<p>

		<table border='1' cellpadding='1' cellspacing='1'><tr><td>Setting Name</td><td>What it does</td></tr>
			<tr><td>Relative URL to Timesheet Entry Page</td><td>This setting is only needed if you are going to publish the time sheet as part of your non-admin pages.  If you want to do this you need to put the shortcode 'entry_timesheet' and 'search_timesheet' in public-facing pages (they will only work for logged in users)</td></tr>
			<tr><td>Week Starts</td><td>This controls what day of the week is the first day listed, and controls if there's an error or not when submitting a timesheet starting on the wrong day of the week.</td></tr>
			<tr><td>Update allowed hours for retainers monthly</td><td>When this is checked the system will automatically update the allowed hours on the 1st of each month for clients with retainers so that the total number of allowed hours for that project includes the new months worth of hours.  Depending on how you use the retainers you may or may not want this to happen.</td></tr>

			<tr><td>Payroll Triggers</td><td>If you are using the payroll queue in the system checking these boxes will kick timesheets into the payroll queue if there's a value other than 0 in the field.  This is handy if you make employees expense certain kinds of expenses.  Things like Mileage and Per Diem being the most common.  'Other Expenses' means the other expenses field in the timesheet.</td></tr></table><p>

		If you have employees that you pay hourly such as subcontractors or just good old hourly employees you need their timesheets to go to payroll even if they don't have expenses.  You can turn this on by using the 'Force Payroll Setup' page.  Simply check the boxes as needed and click 'Save Settings'. Any time sheets that the employee submitted (after being approved) will go into the payroll queue.<p>

		The 'My Settings' page that each employee sees will be different depending on what settings you've enabled in the management screen and what permissions they have within the time sheets system.  For example, if they aren't listed as an 'approver' they won't be able to setup a backup approver or transfer their team to another approver.  The same applies to the 'My Dashboard' page where they will get additional options for searching if they are an 'approver'.<p>

		Hopefully this helps get you started with the system, and helps explain some of the screens that you'll be seeing.
		";
	}
}
