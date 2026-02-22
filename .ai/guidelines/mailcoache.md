---
name: mailcoach
description: Mailcoach guide and integrations
---
# Laravel transactional emails with Mailcoach

By configuring Laravel to send transactional mail through Mailcoach non-technical people can administer the content of your emails, and you gain valuable insights on how your sent emails are performing.

On this page:

    Configuring Laravel
    Editing the content of your emails on Mailcoach
    Viewing the log of sent mails
    In closing

Using Mailcoach, you can send campaigns to lists of any size. It also provides flexible email automation to set up drip campaigns and more.

You can also use Mailcoach to send transactional emails. For Laravel apps, we’ve even created a mail driver that makes it a cinch to use Mailcoach to send emails from your app.

The big benefits of using Mailcoach for sending transactional emails are:

    you can create templates that are easily editable by non-coders on your team

    you can see which emails are opened and which links in the mail are clicked

    you have a searchable log of all emails sent and can easily resend mails.

In this blog post, I’ll guide you through the installation process and showcase all the above benefits.
Configuring Laravel
#

To send transactional emails from your Laravel through Mailcoach, install our Laravel Mailcoach Mailer package.

In your mail.php config file, you must add a mailer in the mailers key that uses the mailcoach transport. You should also specify your Mailcoach domain (this is the first part) and a Mailcoach API token. In many cases, you also want to make Mailcoach the default mailer in your app.

Here’s an example:

// in config/mail.php
'default' => 'mailcoach',
'mailers' => [
'mailcoach' => [
'transport' => 'mailcoach',
'domain' => '<your-mailcoach-subdomain>.mailcoach.app',
'token' => '<your-api-token>',
],
],

php artisan mailcoach-mailer:send-test

This above command will try to send a transactional mail through Mailcoach using your configuration.

Look in your mailbox for the mail sent.

With this setup out of the way, you can send emails like you’re used to.
Editing the content of your emails on Mailcoach
#

Instead of saving the content of your emails in Mailable classes in your app, you can administer them in the Mailcoach UI. This way, non-technical people, such as marketeers, can edit the content of emails without a developer having to push code changes.

To get started, first create a template. A template is used to store the basic layout of your mail. Typically, you would only set this up just once.

Next, you can create an e-mail on the Transactional > Email screen.

On the created email, which we named order-confirmation, you can pick the template you want to use and specify the subject and content of the mail.

As you can see in the screenshot above, you can add placeholders, such as ::productName:: to your mail.

In your Laravel app, here’s how to create a mailable that uses that order-confirmation mail. Don’t forget to apply that UsesMailcoachMail on your mailable.

namespace App\Mails;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Spatie\MailcoachMailer\Concerns\UsesMailcoachMail;
class OrderConfirmationMail extends Mailable
{
use Queueable;
use SerializesModels;
use UsesMailcoachMail;

    public function __construct(
        protected Product $product,
        protected Carbon $shippingDate,
    ) {}

    public function build()
    {
        $this
            ->mailcoachMail('order-confirmation', [
                'productName' => $this->productName,
                'shippingDate' => $this->shippingDate->format('Y-m-d'),
            ]);
    }
}

Now you can send that order confirmation like you’re used to.

Mail::to('happy@customer.com')
->send(new OrderConfirmation($product, $shippingDate));

Should any changes be needed to the copy of the order confirmation, your marketeer can edit the content of the mail on Mailcoach. No code changes are needed in your app.
Viewing the log of sent mails
#

In the Mailcoach UI, you can see a searchable list of all emails sent. If a user of yours lets you know that they didn’t receive a particular mail, you can look up when it was sent and even resend it.

Here’ what the list of sent emails looks like:

When clicking one of the emails, you can see the sent content.

If you enabled open- and click-tracking, you can see when the mail was opened and which links were clicked.

In closing
#

Configuring Laravel to send transactional mail through Mailcoach is very easy. By doing so, non-technical people can administer the content of your emails, and you gain valuable insights on how your sent emails are performing.

# Email lists

A list is a collection of subscribers that have opted into receiving your emails. You may have a list containing the followers of your blog.

On this page:

    What is a list?
    Creating a list
    Adding subscribers
    Adding subscribers using the API
    Double opt-in
    Using a website
    Adding custom attributes

What is a list?
#

A list is a collection of subscribers that have opted into receiving your emails. You may have a list containing the followers of your blog.

There are different ways to add subscribers to your list:

    Add them manually through the interface
    Set up a subscription form
    Add subscribers using the CSV import
    Add subscribers through the API

With Mailcoach self-hosted or Mailcoach, there is no limits on the amount of lists or subscribers.
Creating a list
#

A list is a collection of subscribers that have opted in to receiving your emails.

To create your list, start from the Dashboard and go to the Lists section. There you can click the “Create list” button to start setting up your list.

Creating a list is important because without any list you won’t be able to start creating your campaigns.

Pick a good name for your list, so you can easily identify where your campaigns will be sent to.

    Your list does not have to be very detailed, you can easily use segmentation later.
    Read more about segmentation.

If you don’t have any subscribers yet, it’s a good idea to add your own email address to your list, this way you can start testing campaigns.
Adding subscribers
#

You can add subscribers to your list using different methods:
Importing
#

The fastest way to add a lot of subscribers, for example when moving from a different email marketing service, is to import them from a CSV file.

You can easily import a list of subscribers into an existing list. Upload a CSV file with these columns: email, first_name, last_name and tags, and Mailcoach will start importing these into the list. Additional columns will be added as extra attributes.

When you want to attach multiple tags to a subscriber, use ; as the delimiter. You can follow the progress of the import, see any errors that occurred during the process, and download the uploaded file by using the action menu.

Email addresses that have the unsubscribed status will not be resubscribed when running an import. Confirmation mails will not be sent out to users, even if you have enabled double opt-in for this list. Imported email addresses that had not already unsubscribed, will receive the Confirmed status and will receive any subsequently sent campaigns.

Because these lists can get quite large and an import might take a while, we email you to inform you when Mailcoach has finished importing the subscribers.
Manually
#

You can manually add subscribers through the interface by clicking the “Add subscriber” button on your list’s subscribers overview.
Forms
#

You can accept email list subscriptions coming from external sites by adding a subscription form to that site.

In order to accept incoming form subscriptions you check the “Allow POST from an external form” checkbox on your list’s onboarding settings.

Here’s an example form you can embed on an external site to accept subscriptions:

<form method="POST" action="https://<your-mailcoach-domain>/subscribe/<uuid-of-emaillist>">
    <div>
        <label for="email">Email</label>
        <input name="email">
    </div>
   <!-- optionally you can include the first_name and last_name field
    <div>
        <label for="first_name">First name</label>
        <input name="first_name">
    </div>
    <div>
        <label for="last_name">Last name</label>
        <input name="last_name">
    </div>
    -->

    <!-- Optionally, you can override the confirmation pages -->
    <input type="hidden" name="redirect_after_subscribed" value="https://<your-mailcoach-domain>/subscribed"  />
    <input type="hidden" name="redirect_after_already_subscribed" value="https://<your-mailcoach-domain>/already-subscribed"  />

    <!-- only required if your list has double opt-in enabled
    <input type="hidden" name="redirect_after_subscription_pending" value="https://<your-mailcoach-domain>/redirect-after-pending"  />
    -->
    <div>
       <button type="submit">Subscribe</button>    
    </div>
</form>

Adding tags
#

You can specify one or more tags in an input field named tags that should be attached to the subscriber when it gets created.

<!-- somewhere in your form -->
<input type="hidden" name="tags" value="tagA;tagB">

Make sure to add these tags to the allowed subscriber tags in your onboarding settings

    We highly recommend that you turn on double opt-in for email lists that allow form subscriptions. This will keep your list healthy. Also consider adding a honeypot or spam protection to the form to avoid bots from trying to subscribe.

Importing unsubscribed subscribers
#

If your csv has a column unsubscribed_at with a value, the subscriber will be imported as unsubscribed.
For Mailchimp exports it’s also possible to use unsub_time.
Make sure the value is in the format Y-m-d H:i:s (e.g. 2020-01-01 12:00:00) or another one which can be parsed by Carbon.
Adding subscribers using the API
#

You can also add subscribers using the API, check out the documentation.
Double opt-in
#

Mailcoach enables double opt-in by default on every new email list.
What is double opt-in?
#

When a subscriber is added to the list, an unconfirmed subscription will be created. An email will then be sent to the email address that subscribed. The email contains a link that, when clicked, will confirm the subscription. When a subscription is confirmed, its status will be set to subscribed.

When sending a campaign to an email list, only subscribers that have a confirmed subscription will receive the campaign.
Manually confirming a subscriber
#

In your subscriber overview, you can use the action menu to manually confirm the subscriber.
Re-sending a confirmation email
#

In your subscriber overview, you can use the action menu to re-send the confirmation email to the unconfirmed subscriber.
Using transactional email instead of the default confirmation email
#

You cannot customise the styles of the default confirmation emails. However, you can create a transactional email to be used in the onboarding settings of an email list, and this email will be fully customisable.

You will have access to the following placeholders you can use in the subject and the body of the confirmation email:

::confirmUrl:: The URL where the subscription can be confirmed

::subscriber.first_name:: The first name of the subscriber

::list.name:: The name of this list
Using a website
#

Mailcoach can render a beautiful website for your email list that displays an intro text, a subscription form, and all your past campaigns you sent to that list.

You can enable it in the website settings of your list.

screenshot

Here’s what it looks like for one of our users on Mailcoach. On your self-hosted Mailcoach instance it will look similar.

screenshot

When users click an edition and scroll a bit down, they can see the content of an edition. Here’s the content of an old edition of that newsletter. We automatically display the sent date there, so people have an indication of when the content was written.

screenshot

In the website settings, you can also pick a header image and text, and also choose the base color of your archive. As a nice detail, the form element’s value is displayed in the value’s color.

screenshot

Here’s how the above website looks using different colors.

screenshot

screenshot
Adding custom attributes
#

You can add extra attributes to any subscriber. These attributes can be displayed or used in a conditional when creating a campaign (or other types of mails in Mailcoach).

To get started, head over to the name “Attributes” screen on a subscriber. Here’s how you would add an extra attribute called language to subscriber.

screenshot
https://github.com/spatie/laravel-mailcoach-sdk
# An SDK to easily work with the Mailcoach API in Laravel apps

This package contains the PHP SDK to work with [Mailcoach](https://mailcoach.app). Both self-hosted (v6 and up) and hosted Mailcoach (aka Mailcoach Cloud) are supported. Using this package you can manage email lists, subscribers and campaigns.

Here are a few examples:

```php
use Spatie\MailcoachSdk\Facades\Mailcoach;

// creating a campaign
$campaign = Mailcoach::createCampaign([
    'email_list_uuid' => 'use-a-real-email-list-uuid-here',
    'name' => 'My new campaign',
    'fields' => [
        'title' => 'The title on top of the newsletter',
        'content' => '# Welcome to my newsletter',
    ],
]);

// sending a test of the campaign to the given email address
$campaign->sendTest('john@example.com');

// sending a campaign
$campaign->send();
```

By default, Mailcoach' endpoints will are paginated with a limit of 1000. The package makes it easy to work with paginated resources. Just call `->next()` to get the next page.

```php
// listing all subscribers of a list
$subscribers = $mailcoach->emailList('use-a-real-email-list-uuid-here')->subscribers();

do {
    foreach($subscribers as $subscriber) {
        echo $subscriber->email;
    }
} while($subscribers = $subscribers->next())
```

## Installation

You can install the package via composer:

```bash
composer require spatie/laravel-mailcoach-sdk
```

You must publish the config file with:

```bash
php artisan vendor:publish --tag="mailcoach-sdk-config"
```

This is the contents of the published config file:

```php
return [
    /*
     *  You'll find both the API token and endpoint on Mailcoach'
     *  API tokens screen in the Mailcoach settings.
     */
    'api_token' => env('MAILCOACH_API_TOKEN'),

    'endpoint' => env('MAILCOACH_API_ENDPOINT'),
];
```

In your `.env` file you should add the entries from the config file mentioned above. You'll find both the API token and endpoint on Mailcoach' API tokens screen in the Mailcoach settings.

## Usage

You can use the `Spatie\MailcoachSdk\Facades\Mailcoach` facade to perform most operations.

### Handling pagination

There are several methods, such as `emailLists()`, 'subscribers()' and `campaigns()` to will return paginated results. To get the next page of results just call `next()` on a result. If there are no more results, that method returns `null`.

Here's how you display the email addresses of every subscriber on a list

```php
use Spatie\MailcoachSdk\Facades\Mailcoach;

$subscribers = Mailcoach::subscribers('<email-list-uuid');

do {
    foreach($subscribers as $subscriber) {
        echo $subscriber->email;
    }
} while($subscribers = $subscribers->next())
```

On paginated results, `$subscribers` in the example above there are also some more convenience methods:

- `results()`: get the results. A results object is also iterable, so you can also get to the results by simply using the object in a loop
- `next()`: fetch the next page of results
- `previous()`: fetch the previous page of results
- `currentPage()`: get the current page number
- `total()`: get the total number of results across all pages
- `nextUrl()`: get the URL that will be called to get the next page of results
- `previousUrl()`: get the URL that will be called to get the previous page of results

### Working with email lists

Here's how to get all email lists:

```php
use Spatie\MailcoachSdk\Facades\Mailcoach;

$emailLists = Mailcoach::emailLists();
```

You can get a single email list:

```php
$emailList = $this->mailcoach->emailList('<uuid-of-email-list>');
```

This is how you can create an email list:

```php
use Spatie\MailcoachSdk\Facades\Mailcoach;

Mailcoach::createEmailList(['name' => 'My new email list']);
```

You can get properties of email list:

```php
$emailList->name;
$emailList->uuid;
// ...
```

Take a look at the source code of `Spatie\MailcoachSdk\Resources\EmailList` to see the list of available properties.

You can update an email list by change one of the properties and calling `save()`.

```php
$emailList->name = 'Updated name';
$emailList->save();
```

You can delete an email list by calling `delete()`.

```php
$emailList->delete();
```

### Working with subscribers

To get all subscribers of a list, you can call `subscribers()` on an email list.

```php
use Spatie\MailcoachSdk\Facades\Mailcoach;

$subscribers = Mailcoach::emailList('<uuid-of-email-list>')->subscribers();
```

Optionally, you can pass filters to `subscribers()`. Here how to get all subscribers with a Gmail-address.

```php
use Spatie\MailcoachSdk\Facades\Mailcoach;

$subscribers = Mailcoach::emailList('<uuid-of-email-list>')
   ->subscribers(['filter[email]=gmail.com']);
```

Alternatively, you can call `subscribers()` on  `$mailcoach`

```php
use Spatie\MailcoachSdk\Facades\Mailcoach;

$subscribers = Mailcoach::subscribers('<uuid-of-email-list>', $optionalFilters);
```

There's also a convenience method to quickly get a subscriber from a list.

```php
// returns instance of Spatie\MailcoachSdk\Resources\Subscriber
// or null if the subscriber does not exist.

$subscriber = $emaillist->subscriber('john@example.com');
```
Alternatively, you can get a subscriber by its UUID:

```php
$subscriber = $mailcoach->subscriber('<subscriber-uuid>');
```

This how you can create a subscriber:

```php
use Spatie\MailcoachSdk\Facades\Mailcoach;

$subscriber = Mailcoach::createSubscriber(
    emailListUuid: '<email-list-uuid>',
    attributes: [
        'email' => '<email-address>',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'tags' => ['Newsletter'],
    ]);
```

You can get properties of a subscriber:

```php
$subscriber->firstName;
$subscriber->email;
// ...
```

Take a look at the source code of `Spatie\MailcoachSdk\Resources\Subscriber` to see the list of available properties.

You can update a subscriber by change one of the properties and calling `save()`.

```php
$subscriber->firstName = 'Updated name';
$subscriber->save();
```

You can confirm, unsubscribe and delete a subscriber by calling these methods.

```php
$subscriber->confirm();
$subscriber->unsubscribe();
$subscriber->delete();
```

### Working with campaigns

Here's how to get all campaigns.

```php
use Spatie\MailcoachSdk\Facades\Mailcoach;

$campaigns = Mailcoach::campaigns();
```

You can also get a single campaign();

```php
use Spatie\MailcoachSdk\Facades\Mailcoach;

$campaign = Mailcoach::campaign('<campaign-uuid>');
```

This is how you can create a campaign:

```php
use Spatie\MailcoachSdk\Facades\Mailcoach;

$campaign = Mailcoach::createCampaign([
   'name' => 'My new campaign',
   'subject' => 'Here is some fantastic content for you',
   'email_list_uuid' => '<email-list-uuid>',
   
   // optionally, you can specify the uuid of a template
   'template_uuid' => '<template-uuid>',
   
   // if that template has field, you can pass the values
   // in the `fields` array. If you use the markdown editor,
   // we'll automatically handle any passed markdown
   'fields' => [
        'title' => 'Content for the title place holder',
        'content' => '# My title',
    ],    
]);
```

You can get properties of a campaign:

```php
$campaign->name;
$campaign->subject;
// ...
```

Take a look at the source code of `Spatie\MailcoachSdk\Resources\Campaign` to see the list of available properties.

You can update a campaign by change one of the properties and calling `save()`.

```php
$campaign->name = 'Campaign';
$campaign->save();
```

A test mail will be sent when calling `sendTest()`:

```php
// sending a test to a single person
$campaign->sendTest('john@example.com');

// sending a test to multiple persons
$campaign->sendTest(['john@example.com', 'jane@example.com']);
```

The campaign will be sent to all subscribers of your list, by calling `send()`:

```php
$campaign->send();
```

A campaign can be deleted:

```php
$campaign->delete();
```
