=== Time Sheets ===
Contributors: mrdenny
Donate Link: https://www.dcac.com/go/time-sheets
Tags: ticketing system, time sheets, business management, consulting, workflow, invoicing, payroll, time tracking
Requires at least: 4.7.0
Tested up to: 6.6.2
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A fully configurable time sheet system which allows for employee time tracking, workflows, time sheet approvals, invoicing and payroll processes.

== Description ==

A fully configurable time sheet system which allows for employee time tracking, workflows, time sheet approvals, invoicing and payroll processes.

The system is pretty straight forward to configure.  It supports a basic workflow of employees submitting timesheets to their supervisor, who then approves or denies the time sheet. Once approved the time sheets go over to the accounts receivable queue (we call it the Invoicing Queue) so that the customer can be invoiced.  From there if needed it goes to the payroll queue so that expenses can be paid back to the employee.  There's even a setting for making all invoices that someone submits go to the invoicing queue in case you have hourly employees that you need to handle.

When clients are entered into the system, there is security setup on the clients so that only employees who are working with those clients can see them in their drop down.  This makes the drop down smaller for employees and keeps any third party contractors that are working for you from seeing your entire client list.

The system is configured to allow for retainer projects that get billed automatically and it drops in reminder time sheets to the invoicing queue so that those invoices are invoiced at the beginning of each month.  Reminders are also sent to employees automatically if they have overdue time sheets or if they are working on retainers and they need to get their time sheets in at the end of the month.


== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload the contents of the zip file to the `/wp-content/plugins/time-sheets` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the settings through the settings page.
4. Begin documenting customers, projects and employee access through the various settings pages.


== Frequently Asked Questions ==

= Can I add more workflows? =
No, not at this time.

= Is the Payroll workflow mandatory? =
No, if you don't configure any expense types, and you don't configure any employees to force their time sheets to payroll then the payroll queue will not be shown.

= Is the Invoicing workflow mandatory? =
Depends how you look at things.  If you don't approve anyone for the invoicing queue, but then configure the system for parallel queues for Invoicing and Payroll then the payroll queue will still work but the invoicing queue won't be used.

= Is the Approval Queue mandatory? =
Yes, there's no way to get invoices into the invoicing and/or payroll queues without someone approving them.

= Why is there a fraction shown after the approval and invoicing queues? =
The first number shown is the number of non-retainer time sheets which are pending.  The second number is then number of time sheets on projects which are marked as retainer projects.  The approval may have a third number up there. That is the number of time sheets which are under embargo.  The payroll queue doesn't have a different between retainer and non-retainer time sheets so there's just a single number showing that queue.

= Is there a way to turn off all the retainer settings as we don't need them? = 
Not at this time. If we get some requests to make that an option we'll look into it.

= Can I change the headings on the expenses section? =
No you can't. If you would like this feature added contact us and we'll add it to our backlog.

= When do retainer reminders get sent out? =
They are sent out on the last day of the month. (This is based on the time settings of your web server.)

= When do reminders for late time sheets get sent out? =
They are sent out on Monday mornings. (This is based on the time settings of your web server.)

= What is a work week defined as? =
You can configure it to start on whatever day you'd like. By default it'll configure itself for the week starting on Monday, but you can change it.  It wouldn't be recommneded to change it after starting to use it, as the old timesheets won't be updated.

= How do employees start getting reminders about retainer time sheets being do? =
As soon as they create a time sheet for a retainer project, they'll start getting the reminders.

= How do employees stop getting reminders about retainer time sheets being do? =
If they stop submitting time sheets for 60 days or more, then they will no longer receive the retainer reminders.

= If there's multiple emails of the same time which need to be sent, will they be sent one at a time? =
No, the emails are combined before they are sent out.  This helps minimize the number of emails so the employees don't get a flood of emails.  If the same email subject is queued for an employee within 5 minutes the employee won't get the email until another 5 minutes has passed.

= Can time sheets which have been sent to the invoicing queue be rejected? =
Yes. If an employee has the approval queue and the invoicing queue then they will be able to reject time sheets from the invoicing queue.

= Can time sheets which have been sent to the payroll queue be rejected? =
Yes they can.  Depending on how you have your queue workflows being done this could cause odd things to happen to the timesheet. So be careful.

= How many clients does the system support? =
As many as you need. Your limits are the numbers of rows supported in the database, and the amount of disk space which the database server has.

= How many employees does the system support? =
As many users as WordPress supports in the system. Your limits are the numbers of rows supported in the database, and the amount of disk space which the database server has.

= Where are the settings located? =
The settings are located under the Time Sheets parent menu. This includes the Global settings which are only available to people with the "manage_settings" WordPress settings (admins for example) as well as the "My Settings" page which is available for all users.

= Is there are specific system requirements? =
No. We run this for our business on a database with a single CPU core and a web server with a single CPU core (two machines) and the performance is exactly as expected (your mileage may vary).  If you see performance problems please let us know.

= I have multiple web servers, but I only want to run the email jobs on one, can I? =
Yes you can. Add the code "define( 'TIMESHEETS_SKIP_CRON', false );" to your wp-config.php. This will stop that web server from processing the email messages and just about anything else that's scheduled through cron. Just make sure that you do not put this setting on every web server otherwise all the cron tasks won't run. 

== Screenshots ==

1. Employee time sheet entry form with recurrening timesheets enabled (but non-billable time disabled)
2. List of employees time sheets
3. Adding a new client to the system
4. Employees granted access to a client
5. New Project settings
6. Supervisor time sheet approval screen
7. Invoicing time sheet recording screen
8. Adding users to Approval, Invoicing and Payroll workflow
9. Admin menu header showing various queues with pending invoices
10. Available options on the per employee "My Settings" menu.
11. Global settings (Application tab)
12. Global settings (Display Settings tab)
13. Global settings (Retainer tab)
14. Global settings (Email settings tab)
15. Global settings (Payroll tab)

== Changelog ==
= 2.1.3 =
* Corrected call to $options['show_email_notice'] in cron by adding an isset() around it
* Adjusted step interval for expense fields as the HTML didn't like decimals without the step value

= 2.1.2 =
* Changing numeric fields to numeric data type so the user can't enter text
* Calling the archive function from the email_retainers_due function instead of manually archiving
* Allows for updating of templates
* Adjusting the order of operations when saving a timesheet / template
* Updated the save message to account for saving a template vs timesheet
* Updated FAQ

= 2.1.1 =
* Correct and made consistant the logic around the notes and expeses fields in settings
* Made display notes description more specific
* Added a check all option to the invoicing queue

= 2.1.0 =
* Blocking all cron jobs if the TIMESHEETS_SKIP_CRON setting is set, removing that check from the acutal functions being called by cron
* Adjusted how the email sending function if checking to see if email sending is disabled or not
* Corrected the alias in a select count(*) when inserting retainer timesheets so the alias wasn't a MySQL reserved word
* Corrected the output for debugging when running retainer inserts
* Adding an option to disable cacheing for database queries
* Better escaping if the TIMESHEETS_SKIP_CRON parameter is set in the wp-config.php for the site to disable cron jobs for the timesheets system (only really used in multi-site configurations so that cron jobs can't be fired some a secondary site)
* Added employee title to the Invoicing queue invoice list and viewing a timesheet
* Added null checking to Common.clean_from_db
* Corrected logic around resetting dates for templates whent he show all my templates box is checked, or the my dashboard page is loaded fresh
* Added template intervals for Monthly, Quarterly, and Annualy

= 2.0.1 =
* Enables the ability to require unique project names per client
* Changing the database version

= 2.0.0 =
* Added ability to have retainere projects have a maximum amount instead of a maximum number of hours (for money based retainers, with different rates for different job titles)
* Adjustments to the savings and rejecting of timesheets to support the above money based retainers
* Addresses issue where queued project changes are not being marked as processed correctly
* Turned off ability to set a retainer and either be embargoed or flat rate billing
* Corrected several output messages which were missing "P" HTML tags
* Corrected email_enabled check in function email_on_submission
* Moved archiving of records to a seperate function in order to simplfy code
* Corrected incorrect column names when checking if a timesheet needs to be sent to the payroll queue
* Corrrcted request ID inserting when requesting access to a client
* Corrected issues where invoice were being put into the payroll queue when they shouldn't have been
* Added link after adding project to edit project
* Changed logic so that templates are fired on the day they are due, not the next day



= 1.34.5 =
* Rev database version number
* Fixing logic when firing the cron to run templates

= 1.34.4 =
* Added Monday this week as a default time sheet start date option to the my settings page
* Configred template creation cron job to not be created if the templates feature is disabled
* Add "Show All My Templates" option to the My Dashboard page, when templates are template viewer won't take the date search into account
* Added a dashboard widget which can be displayed by users
* Adjusting nonce code when creating a new timesheet
* Cleaned up parameter checking when editing a project

= 1.34.3 =
* Fixing issue viewing templates from the My Dashboard page
* Fixing issue with the start date on the next usage of the templates being incorrect.

= 1.34.2 =
* Fixing the custom function checking code
* Fixing the start_date value on new time sheets when created from a template
* Fixed bug where new timesheets wouldn't be displayed after saving

= 1.34.1 =
* Fixed a bug where new time sheet templates were showing the timesheet with that number after saving instead of the template.

= 1.34 =
* Fixed display of the invoice list sort order on My Settings page
* Added the ability to queue chaanges to projects
* Removed cron for client based change queue
* Added the ability to create recurring timesheets which will be created automatically
* Fixing several cron jobs that weren't running correctly

= 1.33.7.2 =
* No actual changes, just forcing the changes to go out as the manage-projects.php file isn't being updated, again

= 1.33.7.1 =
* No actual changes, just forcing the changes to go out

= 1.33.7 =
* Corrected issue with the Retainer Interval drop down on new and edited projects

= 1.33.6 =
* Fixed bug with missing $ when referencing a variable
* Fixed bus with incorrect variable name

= 1.33.5 =
* Fixed bugs with the retainers cron job

= 1.33.4 =
* Added javascript to the New Project page for the retainter checkbox

= 1.33.3 = 
* Fixing issue with the notes for project not being copied from the client level to the project level correctly

= 1.33.2 = 
* Correcting retainer type dropdown menus on Invoicing queue
* Removing second per client notes from queues (as that field as been removed)
* Adjusted first retainer report on queue menus to account for retainer types being set per project, not per client
* Adjusted second (was the third) report on the queue menus to better account for retainer types (when viewing only retainers on the invoicing menu)

= 1.33.1 =
* Fixed the spelling of quarterly

= 1.33.0 = 
* Prepping for dynamic retainers to be available in the system
* Moved retainer schedule from client to project level
* Updated cron to handle retainer schedule move
* Removed menu "Recurring Billing" menu option from client / project screens
* Changing the parameter order of the parameters in various emails as Office 365 link protection converts &times into &#215; which causes the email links to break
* Fixed alignment issues when creating or editing a project
* Fixed a pair of variables being used without checking to see if they are valid messages when viewing a blank timesheet
* Correct the method of getting the admin URL, and corrected some links to use non-relative links so they work correctly when timesheets are displayed on front end pages

= 1.32.0 =
* Putting the client name in the emails for access request completion
* Fixing misc unknown varaible warnings when loading timesheets when the settings haven't been set
* Changed groups to users in the display for the "Display Settings" within the global settings
* Removed the unused uploads folder setting field
* Moved the bulk actions button and remove the label to the dropdown menu on the "My Dashboard" page
* Added select all checkbox on the "My Dashboard" page

= 1.31.0.1 =
* Fixing collation issue

= 1.31.0 =
* Added the ability to mark multiple timesheets as "week complete" from the "My Dashboard" screen (this caused some code duplication which will be cleaned up later)
* Added setting to allow users to request access to clients which they don't have access to
* Added setting to allow access to client requests to be auto approved


= 1.30.2.1 =
* Made the list of clients / projects on the My Dashboard menu dynamic based on pending time sheets

= 1.30.2 =
* Disabled the Save Timesheet button after clicking it to prevent duplicate entries
* Made the list of clients / projects on the invoicing / approval menu dynamic so that they only show clients and projects for the timesheets which are in the approval / invoicing queue (and which have been marked as week complete)
* Made the list of clients / projects on the invoicing / approval menu dynamic based on viewing retainers or not

= 1.30.1 =
* Changed action reason for rejected time sheet from UPDATED and REJECTED
* Prevented time sheets which were already invoiced from being rejected or having comments added to them
* Updated emails on timesheet completion to account for admin page being moved

= 1.30.0 =
* Fixed warnings when viewing already created project
* Fixed warnings when changing permissions on a client
* Fixed warnings when updating recurring billing settings for a client
* Fixed warnings when creating a new project
* Fixed wanrings when creating or updating a timesheet
* Fixed dates on the My Dashboard page not showing correctly
* Fixed warning on the closed timesheet search
* Added non-billable hours and a grand total to the My Dashboard page
* Added max project hours to be viewable on the time sheet
* Enabled auditing for when time sheets are updated
* Highlighting over time hours when a time sheet is closed and viewed
* Setting email link when completing ticket to https, if https is being used

= 1.29.4 =
* Fixed warnings when loading the page when logged in, but not in wp-admin

= 1.29.3 =
* Fixed lack of escaping for the email address field for when a project goes over on hours

= 1.29.2 =
* Fixed a bug in the setup.php file with object collation

= 1.29.1 =
* Fixed a bug in the setup.php file with the database object versioning

= 1.29.0 =
* Adjusted error message when rejecting time sheet from the invoicing menu
* Fixed a bug when rejecting time sheet from the invoicing menu
* Fixed a bug when spliting a time sheet
* Added Edit Project to the top drop down menu
* Made Edit Client, Add Project and Edit Project take the current Client when a timesheet has been saved, or when on the Manage Client page
* Allow for queueing of future changes to the monthly billing page
* Add regular_interval column to timesheet_recurring_invoices_monthly_archive table
* Fixed the date picker on the time sheet entry and My Dashboard screens
* Display invoice ID on the time sheet when there is an invoice ID

= 1.28.0 =
* Made the comments on the approval queues wider
* Fixed a bug in the settings.php

= 1.27.0 =
* Added the number of hours used to the warning about being over hours.
* Aligned two halfs of the approval and invoicing menuts
* Enabled invoicing menu to select the retainer type as wanted
* Fixed a couple of undefined index errors

= 1.26.0 =
* Sends reminder email to the time sheet owner 3 days after a timesheet has been rejected (and every 3 days after that), if the time sheet hasn't been resubmitted
* Sends reminder email to the time sheet rejector 7 days after a timesheet has been rejected (and every 7 days after that), if the time sheet hasn't been resubmitted
* Put a couple of line feeds at the bottom of all emails so that email warning messages inserted by corporate email systems look cleaner

= 1.25.2.1 =
* Made the emails on comments include a hyperlink for the ticket number being sent out
* Had to use a different variable in the URL field do to some weirness in email with the old URL parameter, updated show_timesheet to account for this

= 1.25.2 =
* Only create automatic time sheets for projects which are active
* Changed the comment email to include the name of the person who entered the command and the name of the person who entered the timesheet

= 1.25.1 =
* Removed test text from the time sheet screen

= 1.25.0 =
* Added non-billable hours to payroll timesheet list
* Fixed duplicate primary key error when rejecting timesheets from the invoicing menu
* Added option to enter comment and not send email
* Changed a blank value for sales person id to a value of None in the invoining report

= 1.24.0 =
* Fixed the URL for the project properties screen to view time sheets
* Removed users from the menus when their WordPress role is set to "No role for this site"

= 1.23.7.2 =
* Fixed archiving errors for the approval/invoicing/payroll queue

= 1.23.7.1 =
* Removed some line feeds at the end of the files

= 1.23.7 =
* Added Non-Billage time to timesheet with the ability to enable or disable these new fields as needed

= 1.23.6 =
* Added archiving for sent emails
* Added truncating for email queue archive (to keep hard drive space usage in check)

= 1.23.5 =
* Configured to send email on every comment or rejection
* Fixed archive errors when rejecting a timesheet
* Added invoice nunber to payrol queue
* Introduces setting to allow multiple retainers per client
* Added invoiced date to the search options on the closed timesheets screen
* Changed the closed timesheets screen

= 1.23.4 =
* Fixed database error when approving timesheets in parallel queue mode, when writing timesheet to the archive (error would only show up when two inserts were happening within the same milisecond, so it depended on the web servers speed)
* Added sales person and technical sales person to closed timesheet search
* Added sales person and technical sales person when viewing the timesheet

= 1.23.3 =
* Fixed a big where all time sheets showed a warning that there was already a timesheet for that project for that week
* Did the checks for duplicate projects for a week for a user on updates as well as new timesheets

= 1.23.2 =
* Fixed the bug where new time sheets were being saved twice
* The popup calendar's aren't working if WordPress 5.6. Something about the javascript that's being used doesn't work with the changes that WordPress made to the admin portal. Everything else works as expected so marking this as compatible with 5.6.

= 1.23.1 =
* Forced rollback to 1.22.2 due to a bug where new timesheets were saved twice

= 1.23.0 =
* Fixed technical consultant field in the invoicing menu
* Removed a SQL Statement that was showing
* Added a tertiary sort order for the index menu
* Deletes any auto-close entries that are queued when a project is update, if the project is setup to not auto-close
* Fixed the setting "Override Site Wide Date Format" always showing an error that is wasn't set
* Updated the info, warnings and errors to use the admin notices messages instead of the update-nag message type
* Added the ability to prevent having multiple time sheets for a single week for a single project for a single user (this check is only done when submitting new time sheets)
* Added a setting to switch between a warning and an error for duplicate time sheet for a project/week/user
* Fixed the showing off a prior timesheet when submitting a new time sheet and that time sheet has an error (the entire new time sheet process needs to be restarted now)
* Removed a rouge > from the top of the client and project list on the Approval/Invoicing/Payroll queues

= 1.22.2 =
* Changing the way the archive tables are created

= 1.22.1 =
* Changed the archive setup code to only one once, not every time.

= 1.22.0 =
* Made products with no hours and that are inactive show up in the notes field on the approving and invoicing queues
* Removed the setting of the varibles used as global variables
* Fixed close timesheet search from throwing a permissions error when used by someone who doesn't have approver and invoicer rights
* Notes on the settings page which settings are missing which causes a message to be shown on the admin page
* Added auditing capture for all user actions and screens so that a history of all changes that can be initiated by the user are captured into _archive tables. This tables may grow large over time and may need to be maintained.

= 1.21.3 =
* Fixed a bug where the invoicing queue was appearing in the admin menu for all users
* Matched logic on approval queue and the payroll queue to match the invoicing queue

= 1.21.2 = 
* Fixed a bug where the invoicing queue wasn't appearing on the admin menu


= 1.21.1 = 
* Fixed a bug where the invoicing menu wasn't appearing

= 1.21.0 =
* Changed the way checking to see if settings need to be updated after an upgrade is handled
* Removed the duplicate queries, using global variables for them
* Only send retainers entered emails when retainers are entered, not each cycle
* Update the math used to check is a project is over hours, so that retainer projects alert correctly instead of every time

= 1.20.1 =
* Allow admin to set if users can reject time sheets

= 1.20.0 =
* Changed where timsheet rejection and auditing comments are saved
* Allow person who creates the time sheet to be able to reject the time sheet if the time sheet has not been approved.
* Fixed Javascript errors when viewing My Dashboard when not an admin

= 1.19.0 =
* Made the project notes on a timeread editable (making changes here will NOT update the project notes) so that you can copy from the notes if needed

= 1.18.0 =
* Changed the way nonce was being done as it wasn't working on embedded pages
* Made the fields on a new time sheet not remember the values from previous entries
* Made the width of the text boxes on the My Dashboard, Approval and Invoicing menu wider

= 1.17.0 =
* Fixed an "Undefined index" warning when creating a new timesheet, after clicking to save the timesheet
* Made the overtime fields hideable, if they are 0 hours and the setting to hide them is enabled
* Made the OT settings dependant on each other in the UI
* Added the total hours and total expenses to the invoicing list
* Changing to new Date format help URL

= 1.16.1 =
* Fixed a bug causing quarterly recurring invoices to be run each day of the first month of the quarter
* Added some debugging information to the recurrnet job function (output won't ever be seen)

= 1.16.0 =
* Fixing a LOT of "Undefined index" warnings from PHP
* Changed Monthly retainers to recurring invoices
* Added ability to have Weekly, Monthly, or Quarterly recurring invoices (insteaded of just Monthly)
* Changed the cron for monthly invoices (might need to disabled and reinable the plugin because of this)

= 1.15.0 =
* Switching get_current_user_id for wp_get_current_user in the code base
* Switched a few called to the variable $user_id to $user_id->ID
* Fixing a couple of "Undefined index" warnings from PHP

= 1.14.11 =
* Made the top record approval button always visable instead of it being dynamic
* Added Last Billed Day to Approval and Invoicing menus


= 1.14.10.1 =
* Removed extra text from the approval screen

= 1.14.10 =
* Added a total number of hours to the My Time Sheet Dashboard
* Added a total number of hours and expenses to the Approval menu
* Made the top record approval button always visable instead of it being dynamic
* Adding currency to all expense fields other than mileage
* Adding distance metric to show either Miles or Kilometers on the time sheet
* Added currency and distance metric to settings screen
* Added check to the startup to throw an error on the admin screen in the currency or distance metric isn't recorded
* Added currency values to the payroll queue screen
* Added distance metric to the payroll queue screen

= 1.14.9.1 =
* Fixing drop down menu URL

= 1.14.9 =
* Using the client ID that was last edited for the next project, edit project, etc.
* Changing the URL from ./admin.php?entry_timesheet to ./admin.php?show_timesheet
* Changing saving of a timesheet to be done before the content of the admin page is loaded so that the counters in the admin bar are correct when any user saves a timesheet
* Changes the saving and time sheet entry functions based on the new method of saving
* There's a few extra functions that need to be cleaned up later

= 1.14.8 =
* Fixed a missing "TR" tag on the approval queue page
* Added the number of hours used, and the expense total to the approval queue page

= 1.14.7.1 =
* Fixed the embargo check box on the time sheet entry page

= 1.14.7 =
* Made Auto Close a project the default when creating a new project
* Changed the way the options are processed for a new project
* When a time sheet is rejected the hours used are removed from the project
* Recalulicate hours used for each project (depending on how many partial hours were entered on each time sheet this could show a large difference)

= 1.14.6.1 =
* Fixed bug where time sheets weren't updated correctly.

= 1.14.6 =
* Added the the ability to have overtime listed as a seperate line item on each timesheet
* Made the abilitly to have overtime an option setting for the system
* Added in an option to have a multiplier for Overtime for counting hours purposes for a project (1 hour of OT counts against a project as 1.5 hours of time for example). The multiplier can be adjusted as needed
* Changed the datatype for total hours used on a project from BIGINT to NUMERIC(12,2) to account for the modifier
* Project properties now shows the hours used with a decimal
* Recalulicate hours used for each project based on the new data type (depending on how many partial hours were entered on each time sheet this could show a large difference)

= 1.14.5 =
* Added emails when a comment is added for a closed time sheet
* Changed the way you can stop multipe servers from doing backround jobs by adding "define( 'TIMESHEETS_SKIP_CRON', false );" to the wp-config.php file.

= 1.14.4 =
* Fixing cron checks

= 1.14.3 =
* Force the monthly notices to be inserted once (only used if the site is hosted on multiple servers)

= 1.14.2 =
* Fixed a bug when timesheets which are rejected when being invoiced weren't being removed from the queue even though they were rejected

= 1.14.1 =
* Fixed a bug where retainers would be closed automatically
* Automatically marked all retainers as active projects again

= 1.14.0 =
* Added a Taxi/Rideshare expence column to the timesheet and database

= 1.13.0 =
* Corrected the top button on the approval screen
* Added in the ability for projects to be closed automatically when all the hours on the project are used
* Open projects that were auto-closed if a time sheet is rejected
* Enabled a setting to set new project to be auto-closed
* Fixed monthly job which resets the hours available on retainer projects

= 1.12.1 =
* Fixed a bug where having notes visable on closed timesheets would prevent the project for the closed timesheet from being shown.

= 1.12.0 =
* Added tracking notes to be added to completed time sheets
* Added the ability to reject a timesheet when ading note which sends the comment to the person who entered the timesheet
* Added a setting to the display tab to allow hiding or showing of notes on completed time sheets as needed
* Rejecting a completed time sheet requires a reason

= 1.11.0 =
* Made the Global Settings page tab driven, allowing the settings page to be smaller and more focused

= 1.10.1 =
* Fixed the closed invoices search screen to show and hide the client and project columns correctly

= 1.10.0 =
* Fixed declaration of variables in the main file
* Make new project active by default
* Added customer permissions to the "Add Customer" screen as well as the edit customer screen

= 1.9.1 =
* Removed client and project columns from "Closed Timesheets" screen if there's only one active project in the system
* Removed client search option on the "Closed Timesheets" screen if there's only one active project in the system
* Added a "My Settings" option to show partial notes on the time sheet on the "Closed Timesheets" screen, and updated that screen to account for this checkbox

= 1.9.0 =
* Fixed ability to embed search and new timesheets in a page and have them show up correctly, under other content
* Fixed having no ClientId when hiding the client and project drop down menu.
* Fixed the Timesheet_Search shortcode

= 1.8.0 =
* Fixed an query being used by the closed timesheets screen
* Fixed an issued where the ability to hide the client and project when there's only one available wasn't working

= 1.7.10 =
* Added the ability to search and view closed time sheets. 
* Changed our URL from dcac.co to dcac.com.

= 1.7.9 =
* Added feature to alert when a project is close to runnning out of hours (you define close).
* Added settings fields to support when a project is running out of hours.
* Changed alert when project is running out of hours to only queue email on timesheet viewing (when the timesheet is open).

= 1.7.8.1 =
* Fixed a bug where the enbarged timesheets were shown on the approval page by default.

= 1.7.8 =
* Changed approval screen to show either embargoed timesheets or non-embargoed time sheets

= 1.7.7 =
* Allow Monthly edit screen to have line breaks
* Make project notes on the timesheet bigger.


= 1.7.6 =
* Changed the way that the message on startup to thrown if a needed setting is missed
* Added Technical Sales Person to Client and Project

= 1.7.5 =
* Fixed bug where new timesheets when saves and closed aren't showing propertly
allowing them to be double saved.

= 1.7.4 =
* Updating My Time Sheet Dashboard quantity shown in real time as the timesheets are saved

= 1.7.3 =
* Projects are deactivated when a timesheet is marked as a closed project
* Added a New Time Sheet link under the My Time Sheet Dashboard link
* Added client management under the My Time Sheet Dashboard link
* Removed New Time Sheet from the New menu unless the My Time Sheet Dashboard link is not shown

= 1.7.2 =
* Fixed bug to approve imbargoed time sheets

= 1.7.1 =
* Fixed updated code

= 1.7.0 =
* Added Sales Person dropdown to Client and Project
* Added Sales Person to invoicing queue
* Added Sales Person priority to settings page
* Added check for sales person setting to main screen


= 1.6.18 =
* Removed test from the entry form.
* Fixed monthy retainer emails and resetting of clients.

= 1.6.17 =
* Fixes the approvals menu so embargoes time sheets can be updated correctly.

= 1.6.16 =
* In the approval menu, clicking approve, reject or hold will set all time sheets to that settings.

= 1.6.15 =
* Fixed review screen so it works from all approval menus.

= 1.6.14 =
* Week Complete field on My Dashboard is now centered.

= 1.6.13 =
* Fixed permissions on dashboard so regular users couldn't see other people time sheets.
* Fixed dashboard so search worked properly.
* Change "Setup Approval Teams" so it doesn't show debug information when adding a team member.


= 1.6.12 =
* Fixed permissions on Manage Clients screen so the Manage Clients permissions actually work.
* Added PO Number to new project screen.

= 1.6.11.1 =
* No code change, but some updated gaphics to push down and a new readme.

= 1.6.11 =
* Added po number as a project field and display it on the invoicing screen.

= 1.6.10 =
* Added Hours to My Dashboard and to Payroll Queue.

= 1.6.9 =
* Fixing My Settings so it works for people who are only Invoing Queue users.

= 1.6.8.1 =
* Fixing 1.6.8 so it actually works.

= 1.6.8 =
* Allow invocing users to decide to put the "Record Invoicing" button at the top of the list all the time.

= 1.6.7 =
* Documentation!!!!!!!
* Make monthly retainers not show up in the payroll queue at the top of the month.
* Removed comments from time_sheets_cron.email_retainers_due in a vauge attempt to make it work correctly each month.

= 1.6.6 =
* Changed size of currency fields to support larger amounts.

= 1.6.5 =
* Fixed bug where timesheet queues weren't being loaded in parallel correctly, and timesheets could be sent to the payroll queue twice.

= 1.6.4 =
* Fixed bug where the submit button wasn't visible on new time sheets
* Fixed bug where all timesheets were being sent to payroll queue


= 1.6.3 =
* Changed label in the admin bar to My Time Sheet Dashboard
* Removed Submit button if viewing another users time sheet
* Added logic to prevent updating another users time sheet


= 1.6.2 =
* Fixed bug where the Project drop down doesn't work in the time sheet screen.

= 1.6.1 =
* Fixed bugs in query of My Dashboard where it was showing all employees at initial load instead of just that persons.
* Changed text in My Dashboard if not matches are found.


= 1.6 =
* Display project name when project is over hours
* Rename Old Timesheets to My Dashboard
* Add additional filters to My Dashboard (all employees, my time sheets, specific team member, all team members, filter by client and project)
* Display employees client notes and project names on My Dashboard
* Updated screenshot of My Dashboard (Screenshot 2)

= 1.5.17 =
* Fixed bug where the payrol queue is showing more invoices then it should if you are using parallel workflows.

= 1.5.16 =
* Added the ability to split the queue process so that Payroll can be processed before invoicing.

= 1.5.15 =
* Hack to make the dates on the entry page correct on matter what timezone it is. This isn't a perfect fix, but it'll do for now.

= 1.5.14 =
* Made the week start date configurable.
* Made the dates for the work days in the timesheet change on the fly based on the date selected (I hate JavaScript).
* Added checks for the new setting on startup.
* Resetting the cron so that the cron jobs fire at the correct time after the 1.5.13 upgrade.

= 1.5.13 =
* Added clarity to the screens granting access to parts of the system.
* Fixed the crons so that weekly reminders go out on Monday instead of being based on the time that the plugin was last updated.
* Corrected minimum required version of WordPress to 4.7.0.

= 1.5.12 =
* Fixed issue with employees who need to always need to go to payrol queue, and the project was set to skip the invoicing queue didn't make it into the payrol queue.

= 1.5.11 =
* Added Hold option to approver menu.

= 1.5.10 =
* Disable save new project button disabled after saving, to prevent duplicate projects from being saved.

= 1.5.9 =
* Better cleaning of single quotes in text fields to get rid of the escape chatacter that wordpress and PHP like to stick in there.
* Changed label on filter button for invoincing menu.
* Added submit buttons above the approval and invoicing lists when the list is long.
* Fixed sorting issues on various employee lists.
* Added Display Name to client permissions page and sorted by Display Name.

= 1.5.8 =
* Adding option to My Settings page for people who have access to the Invoicing queue to allow custom sorting of the output for easier invoicing based on user needs.

= 1.5.7 =
* Fixed problem with invoicing menu not saving changes. 
* Changed label on filter button for invoincing menu.
* Removed un-needed/un-used functions from primary file.

= 1.5.6 =
* Remove clients with no active project from time sheet client list.

= 1.5.5 =
* Fixed label on submit button on approval queue.

= 1.5.4 =
* Added ability to set project as flat rate billing do time sheets bypass the invoicing queue.
* Updated FAQ

= 1.5.3 =
* Added ability to create a new time sheet from the "New" menu in the admin bar (who can see that menu).
* Made the javascript a little more dynamic to account for features being turned on or off.

= 1.5.2 =
* Resolved additional potential cross site scripting vunerabilities.
* Removed some un-needed code (the calls had already been moved to common, but the old functions were still sitting there).
* Added a setting to remove the embargo feature if it isn't needed.
* Little cleanup in the client list code for the client management screens.

= 1.5.1 =
* Resovled issue with monthly cron job not updating retainer hours.

= 1.5.0 =
* Fixed possible cross site scripting in old timesheet list.
* Added feature to allow for hiding client and project dropdown if only one client and project are available.
* Included pollyfil.js and all needed files.
* Changed to internal jquery not external.

= 1.4.2 =
* Set the date picker for all date fields.
* Moved date picker CSS and Javascript calls to common class.

= 1.4.1 =
* Fixed formatting issue in settings
* Converted monthly notes to text box to minimize space being used by them while allowing for basically as much text as is desired.

= 1.4.0 =
* Clean up errors to make them more visible.
* Add data cleanup to date fields when searching and viewing client list.
* Fixed split time sheet from the approval menu.
* Added calendar popup to time sheet entry.
* Added warning when time sheet isn't starting on a Monday.
* Allow admin to customize date format.
* Allow admin to allow users to specify their own date format.
* Changed client and project notes to text boxes to minimize space being used by them while allowing for basically as much text as is desired.
* Updated FAQ

= 1.3.1 =
* Fixed issue where time sheets weren't saving or throwing an error message. They save now.

= 1.3 =
* Fixed Pier Diem days field converting to an integer. Now accepts decimals.
* Added option "Open Time Sheets" to header.
* Added ability to put new time sheet within a page using shortcut code timesheet_entry.
* Added ability to put new time sheet within a page using shortcut code timesheet_search.
* Increased security on the new and search time sheet pages due to the public short codes being available for use.
* Added setting to support the link redirection that needs to happen when using the timesheet_search shortcut code.


= 1.2.1 =
* Fixed menu for editing client not working correctly.

= 1.2 =
* Changed Edit Client Permissions to Edit Client and added the ability to change a client's name.

= 1.1.1 =
* Max hours on retainer projects now adjust monthly based on the hours used plus the available hours per month so the project alerts are accurate for retainer projects.
* Max hours on retainer projects not editable anymore.

= 1.1 =
* Major code refactoring.
* Cleaning up display names and user names.

= 1.0.2 =
* Fixed bug in invoicing where if time sheets were given an invoice number, but not marked as processed they could loose their invoice number when working on other invoices.
* Added ability to turn off notes and expenses sections for teams.
* Added ability to add a backup approver for teams (perfect for approvers who take vacations, or have an assistant).
* Added screen to manage those who can add customers and projects.

= 1.0.1 =
* Added per diem city to time sheets.

= 1.0 =
* First release

== Upgrade Notice ==


