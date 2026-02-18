<?php

namespace App\Tests\Sending\Email\InboundParse;

use App\AccountsReceivable\Models\Customer;
use App\Core\Authentication\Models\User;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Model;
use App\Core\Statsd\StatsdClient;
use App\Sending\Email\InboundParse\Handlers\InboxEmailHandler;
use App\Sending\Email\Models\EmailParticipant;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\InboxEmail;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

class InboxEmailHandlerTest extends AppTestCase
{
    private static ?Model $requester;
    private static User $originalUser;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInbox();

        self::$requester = ACLModelRequester::get();
        self::$originalUser = self::getService('test.user_context')->get();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::getService('test.user_context')->set(self::$originalUser);
        ACLModelRequester::set(self::$requester);
    }

    public function testCustomerMatching(): void
    {
        $customerEmail = 'customer@invoiced.com';
        $parameters = [
            'to' => 'abcdefghij@test.invoicedmail.com, abcdefghij@test.invoicedmail.cox',
            'from' => "Bob Loblaw <$customerEmail>",
            'subject' => 'Customer matching',
            'text' => 'Body email text...',
            'headers' => <<<EOD
                Received: by mx0052p1mdw1.sendgrid.net with SMTP id nnWSpA4aET Wed, 26 Feb 2020 16:15:21 +0000 (UTC)
                Received: from mail-qk1-f180.google.com (mail-qk1-f180.google.com [209.85.222.180]) by mx0052p1mdw1.sendgrid.net (Postfix) with ESMTPS id A35B05C13B5 for <abcdefghij@test.invoicedmail.com>; Wed, 26 Feb 2020 16:15:13 +0000 (UTC)
                Received: by mail-qk1-f180.google.com with SMTP id m2so1496851qka.7 for <abcdefghij@test.invoicedmail.com>; Wed, 26 Feb 2020 08:15:13 -0800 (PST)
                DKIM-Signature: v=1; a=rsa-sha256; c=relaxed/relaxed; d=invoiced.com; s=google; h=mime-version:from:date:message-id:subject:to; bh=UPRHD8UyJbUsPslMusRkSmJWPrDnT61oq2bh0/hNFvg=; b=FrDm8B62EZVrhW35I5x7Jhj7ga0wLFL2ITiT1H3mufmegmumzKS1zD/GqkmbEQciuq jl80+lzLEs8RyVAcO64BBpfl/yJlK8kd15dmIdA03XgM1JSjbVvwIl2hBOJA76KKJQUk Y9twKPmpcwDIEnxtGiyuzQHLpGl8WJDsyKDLw=
                X-Google-DKIM-Signature: v=1; a=rsa-sha256; c=relaxed/relaxed; d=1e100.net; s=20161025; h=x-gm-message-state:mime-version:from:date:message-id:subject:to; bh=UPRHD8UyJbUsPslMusRkSmJWPrDnT61oq2bh0/hNFvg=; b=gRhER3yLXCjcmHC8le4Qx+rA5HDTYixJuP43RBcdu7gmV5lV++U/HsRS29cisP6HKE AX/tOVEyLJAkxvbgUtIKR0JTNCgCkB/rg2x1eP+xN0Y0Qj1AooK4Nhdi3es7+Ua309ys 2og+faTXDV6ZeLEmNchgcJk1KMVME92FpWO+89uymbkhnFhc99oUd3mMtsMkZ4EZkwA5 Gm+JG6pIM5EOGX/ekwD0eoalPq3M6yjAdDlchdOmpz9YXbzSBgL9dImlukTzgLkkQaoS mipqeKjcXf5A7RugwwIomcDLh6u7k9OaZWtG137s3z24UopuIQEoZ8yIdASVGSNuPovN Ondw==
                X-Gm-Message-State: APjAAAVplh6ngn4KtCHDvhDkigNLaTwyp20sOARWpOVy0MXVImYW2Ti/ 8f7jV47NLcL8bH0un1JmvAAFHOZ/aNb73wR1ibXJY+Ovt/o=
                X-Google-Smtp-Source: APXvYqzEaTzRm+GnNwqm/dVQlgOi9w6iPoRWy8b6PFNfYBPMdDUEgoJh4zKuntz2jbWiGgQuBbCD0W+uWBSnGX8Un7g=
                X-Received: by 2002:a37:a2ce:: with SMTP id l197mr5982679qke.295.1582733712408; Wed, 26 Feb 2020 08:15:12 -0800 (PST)
                MIME-Version: 1.0
                From: Bob Loblaw <bob@example.com>
                Date: Wed, 26 Feb 2020 10:15:01 -0600
                Subject: SUBJECT
                To: abcdefghij@test.invoicedmail.com
                Content-Type: multipart/mixed; boundary="000000000000b40bfc059f7ce8ea"
                EOD,
            'html' => '<div dir="ltr">Body<div>hello</div><div>test</div><div>bye</div><div><br clear="all"><div><div dir="ltr" class="gmail_signature" data-smartmail="gmail_signature"><div dir="ltr"><div><div dir="ltr"><div style="color:rgb(136,136,136)"><font size="2">Bob Loblaw<br></font></div><div style="color:rgb(136,136,136)"><i>Software Engineer<br></i></div><div style="color:rgb(136,136,136)"><i><img src="https://drive.google.com/a/invoiced.com/uc?id=1wNbxuyp-5fhNQBJRJFaOLk_QBOwuqZ5c&amp;export=download" width="96" height="24"><br></i></div><div style="color:rgb(136,136,136)"><font size="2">(a) 5301 Southwest Parkway, Suite 470, Austin, TX 78735</font></div><div><font size="2" style="color:rgb(136,136,136)">(p) </font><font color="#888888">(512) 270-3227</font></div><div style="color:rgb(136,136,136)"><font size="2">(e) <span style="color:rgb(56,118,29)"><font color="#1155cc"><a href="mailto:bob@example.com" target="_blank">bob@example.com</a></font><br></span></font></div><div style="color:rgb(136,136,136)"><font size="2"><br></font></div><div style="color:rgb(136,136,136)"><font size="2"><a href="https://invoiced.com/" style="color:rgb(17,85,204)" target="_blank">Invoiced.com</a> | <a href="https://twitter.com/invoicedapp" style="color:rgb(17,85,204)" target="_blank">Twitter</a> | <a href="https://www.linkedin.com/companies/invoiced" style="color:rgb(17,85,204)" target="_blank">LinkedIn</a></font></div></div></div></div></div></div></div></div>',
        ];

        $request = Request::create('/sendgrid/inbound', 'POST', $parameters);

        $this->getHandler()->processEmail($request);

        $thread = EmailThread::where('name', 'Customer matching')
            ->where('inbox_id', self::$inbox)
            ->one();

        // no customer match
        $this->assertNull($thread->customer_id);
        $thread->delete();

        $request = Request::create('/sendgrid/inbound', 'POST', $parameters);
        // one customer match
        $customer = new Customer();
        $customer->email = $customerEmail;
        $customer->name = 'test1';
        $customer->country = 'US';
        $customer->saveOrFail();
        $this->getHandler()->processEmail($request);

        $thread = EmailThread::where('name', 'Customer matching')
            ->where('inbox_id', self::$inbox)
            ->one();
        $this->assertEquals($customer->id, $thread->customer_id);
        $thread->delete();

        $request = Request::create('/sendgrid/inbound', 'POST', $parameters);
        // multiply customers match
        $customer = new Customer();
        $customer->email = $customerEmail;
        $customer->name = 'test2';
        $customer->country = 'US';
        $customer->saveOrFail();
        $this->getHandler()->processEmail($request);
        $thread = EmailThread::where('name', 'Customer matching')
            ->where('inbox_id', self::$inbox)
            ->one();
        $this->assertNull($thread->customer_id);
        $thread->delete();
    }

    public function testProcessInboxEmail(): void
    {
        $parameters = [
            'to' => 'abcdefghij@test.invoicedmail.com, abcdefghij@test.invoicedmail.cox',
            'from' => 'Bob Loblaw <bob@example.com>',
            'subject' => 'SUBJECT',
            'text' => 'Body email text...',
            'headers' => <<<EOD
                Received: by mx0052p1mdw1.sendgrid.net with SMTP id nnWSpA4aET Wed, 26 Feb 2020 16:15:21 +0000 (UTC)
                Received: from mail-qk1-f180.google.com (mail-qk1-f180.google.com [209.85.222.180]) by mx0052p1mdw1.sendgrid.net (Postfix) with ESMTPS id A35B05C13B5 for <abcdefghij@test.invoicedmail.com>; Wed, 26 Feb 2020 16:15:13 +0000 (UTC)
                Received: by mail-qk1-f180.google.com with SMTP id m2so1496851qka.7 for <abcdefghij@test.invoicedmail.com>; Wed, 26 Feb 2020 08:15:13 -0800 (PST)
                DKIM-Signature: v=1; a=rsa-sha256; c=relaxed/relaxed; d=invoiced.com; s=google; h=mime-version:from:date:message-id:subject:to; bh=UPRHD8UyJbUsPslMusRkSmJWPrDnT61oq2bh0/hNFvg=; b=FrDm8B62EZVrhW35I5x7Jhj7ga0wLFL2ITiT1H3mufmegmumzKS1zD/GqkmbEQciuq jl80+lzLEs8RyVAcO64BBpfl/yJlK8kd15dmIdA03XgM1JSjbVvwIl2hBOJA76KKJQUk Y9twKPmpcwDIEnxtGiyuzQHLpGl8WJDsyKDLw=
                X-Google-DKIM-Signature: v=1; a=rsa-sha256; c=relaxed/relaxed; d=1e100.net; s=20161025; h=x-gm-message-state:mime-version:from:date:message-id:subject:to; bh=UPRHD8UyJbUsPslMusRkSmJWPrDnT61oq2bh0/hNFvg=; b=gRhER3yLXCjcmHC8le4Qx+rA5HDTYixJuP43RBcdu7gmV5lV++U/HsRS29cisP6HKE AX/tOVEyLJAkxvbgUtIKR0JTNCgCkB/rg2x1eP+xN0Y0Qj1AooK4Nhdi3es7+Ua309ys 2og+faTXDV6ZeLEmNchgcJk1KMVME92FpWO+89uymbkhnFhc99oUd3mMtsMkZ4EZkwA5 Gm+JG6pIM5EOGX/ekwD0eoalPq3M6yjAdDlchdOmpz9YXbzSBgL9dImlukTzgLkkQaoS mipqeKjcXf5A7RugwwIomcDLh6u7k9OaZWtG137s3z24UopuIQEoZ8yIdASVGSNuPovN Ondw==
                X-Gm-Message-State: APjAAAVplh6ngn4KtCHDvhDkigNLaTwyp20sOARWpOVy0MXVImYW2Ti/ 8f7jV47NLcL8bH0un1JmvAAFHOZ/aNb73wR1ibXJY+Ovt/o=
                X-Google-Smtp-Source: APXvYqzEaTzRm+GnNwqm/dVQlgOi9w6iPoRWy8b6PFNfYBPMdDUEgoJh4zKuntz2jbWiGgQuBbCD0W+uWBSnGX8Un7g=
                X-Received: by 2002:a37:a2ce:: with SMTP id l197mr5982679qke.295.1582733712408; Wed, 26 Feb 2020 08:15:12 -0800 (PST)
                MIME-Version: 1.0
                From: Bob Loblaw <bob@example.com>
                Date: Wed, 26 Feb 2020 10:15:01 -0600
                Message-ID: <CAMNO8JwH=mcTSOvtvLhHhrhvaS+g3irNJOndggaSSQH_fxNU=g@mail.gmail.com>
                Subject: SUBJECT
                To: abcdefghij@test.invoicedmail.com
                Content-Type: multipart/mixed; boundary="000000000000b40bfc059f7ce8ea"
                EOD,
            'html' => '<div dir="ltr">Body<div>hello</div><div>test</div><div>bye</div><div><br clear="all"><div><div dir="ltr" class="gmail_signature" data-smartmail="gmail_signature"><div dir="ltr"><div><div dir="ltr"><div style="color:rgb(136,136,136)"><font size="2">Bob Loblaw<br></font></div><div style="color:rgb(136,136,136)"><i>Software Engineer<br></i></div><div style="color:rgb(136,136,136)"><i><img src="https://drive.google.com/a/invoiced.com/uc?id=1wNbxuyp-5fhNQBJRJFaOLk_QBOwuqZ5c&amp;export=download" width="96" height="24"><br></i></div><div style="color:rgb(136,136,136)"><font size="2">(a) 5301 Southwest Parkway, Suite 470, Austin, TX 78735</font></div><div><font size="2" style="color:rgb(136,136,136)">(p) </font><font color="#888888">(512) 270-3227</font></div><div style="color:rgb(136,136,136)"><font size="2">(e) <span style="color:rgb(56,118,29)"><font color="#1155cc"><a href="mailto:bob@example.com" target="_blank">bob@example.com</a></font><br></span></font></div><div style="color:rgb(136,136,136)"><font size="2"><br></font></div><div style="color:rgb(136,136,136)"><font size="2"><a href="https://invoiced.com/" style="color:rgb(17,85,204)" target="_blank">Invoiced.com</a> | <a href="https://twitter.com/invoicedapp" style="color:rgb(17,85,204)" target="_blank">Twitter</a> | <a href="https://www.linkedin.com/companies/invoiced" style="color:rgb(17,85,204)" target="_blank">LinkedIn</a></font></div></div></div></div></div></div></div></div>',
        ];

        $request = Request::create('/sendgrid/inbound', 'POST', $parameters);

        $this->getHandler()->processEmail($request);

        // should create an email thread
        /** @var EmailThread $thread */
        $thread = EmailThread::where('name', 'SUBJECT')
            ->where('inbox_id', self::$inbox)
            ->oneOrNull();
        $this->assertInstanceOf(EmailThread::class, $thread);

        // should create an email
        /** @var InboxEmail $email */
        $email = InboxEmail::where('subject', 'SUBJECT')
            ->where('thread_id', $thread)
            ->oneOrNull();
        $this->assertInstanceOf(InboxEmail::class, $email);
        $this->assertEquals('<CAMNO8JwH=mcTSOvtvLhHhrhvaS+g3irNJOndggaSSQH_fxNU=g@mail.gmail.com>', $email->message_id);

        // should create participants
        $from = $email->from;
        $this->assertEquals('bob@example.com', $from['email_address']);
        $this->assertEquals('Bob Loblaw', $from['name']);

        $toParticipants = $email->to[0];
        $this->assertEquals('abcdefghij@test.invoicedmail.com', $toParticipants['email_address']);
    }

    /**
     * @depends testProcessInboxEmail
     */
    public function testProcessInboxEmailReply(): void
    {
        $parameters = [
            'to' => 'abcdefghij@test.invoicedmail.com',
            'from' => 'Bob Loblaw <bob@example.com>',
            'subject' => 'Re: SUBJECT',
            'text' => 'This is a follow up to my previous email...',
            'headers' => <<<EOD
                Received: by mx0049p1mdw1.sendgrid.net with SMTP id sm5AWJRtJN Wed, 26 Feb 2020 16:25:28 +0000 (UTC)
                Received: from mail-qv1-f43.google.com (mail-qv1-f43.google.com [209.85.219.43]) by mx0049p1mdw1.sendgrid.net (Postfix) with ESMTPS id E8F3EA89E05 for <abcdefghij@test.invoicedmail.com>; Wed, 26 Feb 2020 16:25:27 +0000 (UTC)
                Received: by mail-qv1-f43.google.com with SMTP id p2so17286qvo.10 for <abcdefghij@test.invoicedmail.com>; Wed, 26 Feb 2020 08:25:27 -0800 (PST)
                DKIM-Signature: v=1; a=rsa-sha256; c=relaxed/relaxed; d=invoiced.com; s=google; h=mime-version:references:in-reply-to:from:date:message-id:subject:to; bh=WuX5ebl3bHVfgLeDIIAkXJdg3eF0MPePR9UlqhUMyvI=; b=FkBnIquGFvJnxeWyxbiPCgdWnZsAh8tUS04tB10edVzCY/sTQwChHnEakZdEgnxOuD QbN6rDcqZNjl8IwDuXVIenj9+VoYezByNZWyGQiej2kJz+4PgzKB+mhsEMMbJb4enlpl ZfaWcU3Uoi/LFug0l5ROMgXubtXD2WHOm7naQ=
                X-Google-DKIM-Signature: v=1; a=rsa-sha256; c=relaxed/relaxed; d=1e100.net; s=20161025; h=x-gm-message-state:mime-version:references:in-reply-to:from:date :message-id:subject:to; bh=WuX5ebl3bHVfgLeDIIAkXJdg3eF0MPePR9UlqhUMyvI=; b=Fu0aquZboEQv4uxLnePouTuYK6JhXUH6Cm62QGtXI/cL+cOMCLsIPO3x9Kb/cEucTH jeS8P6DyVGfIt8sqehA1XmqyWI4mEG+IZ8DL85QjwtDR8QSEBEMvDj2BONoWjLUFG7aE SCKr7wbrfFf5+n5ZeK/ZIXOGI0EQ0yADAluTngNbx4rF85TMG6RruPVjnR2xSoGDoBtX DHYA7rxewhPpmWV0bDUjKgvn2VTcHvVeeKLGdog/jlYdXXY+/8Uzx+WtDG7+0tQFHtGY Jy6HRWP+7jrbLfaKIRnA2y5JlVc+H0R1E3b/2uLDbQEUfsBi55iTu0Mlq9MsPUClEn9W H4aw==
                X-Gm-Message-State: APjAAAUG6wx3fA4sQiK2bpYynQml+5Gl0H5vlcTaUKKUlQRaLGH3+s6R /7PWGHrrBwHJJsPPczPN8Kq382KUfF2lbXxvRjx1Jbs+vLE=
                X-Google-Smtp-Source: APXvYqxJfNb49G/L/GCv0yNkHnqJJMlvzwzTVafLaJrQw4NaueaSJWA5zDRdEa59pIT8Ki4OnoNG1m+VwK+v2I/X6J0=
                X-Received: by 2002:ad4:5663:: with SMTP id bm3mr5943114qvb.110.1582734325264; Wed, 26 Feb 2020 08:25:25 -0800 (PST)
                MIME-Version: 1.0
                References: <CAMNO8JwH=mcTSOvtvLhHhrhvaS+g3irNJOndggaSSQH_fxNU=g@mail.gmail.com>
                In-Reply-To: <CAMNO8JwH=mcTSOvtvLhHhrhvaS+g3irNJOndggaSSQH_fxNU=g@mail.gmail.com>
                From: Bob Loblaw <bob@example.com>
                Date: Wed, 26 Feb 2020 10:25:14 -0600
                Message-ID: <CAMNO8JyXN6b9YvE3pOjojdb1kbunZYOrbj8m7Hgx4a3Up4G5Ug@mail.gmail.com>
                Subject: Re: SUBJECT
                To: abcdefghij@test.invoicedmail.com
                Content-Type: multipart/alternative; boundary="0000000000003b470f059f7d0df7"
                EOD,
            'html' => '<div dir="ltr">Testing reply<br clear="all"><div><div dir="ltr" class="gmail_signature" data-smartmail="gmail_signature"><div dir="ltr"><div><div dir="ltr"><div style="color:rgb(136,136,136)"><font size="2">Bob Loblaw<br></font></div><div style="color:rgb(136,136,136)"><i>Software Engineer<br></i></div><div style="color:rgb(136,136,136)"><i><img src="https://drive.google.com/a/invoiced.com/uc?id=1wNbxuyp-5fhNQBJRJFaOLk_QBOwuqZ5c&amp;export=download" width="96" height="24"><br></i></div><div style="color:rgb(136,136,136)"><font size="2">(a) 5301 Southwest Parkway, Suite 470, Austin, TX 78735</font></div><div><font size="2" style="color:rgb(136,136,136)">(p) </font><font color="#888888">(512) 270-3227</font></div><div style="color:rgb(136,136,136)"><font size="2">(e) <span style="color:rgb(56,118,29)"><font color="#1155cc"><a href="mailto:bob@example.com" target="_blank">bob@example.com</a></font><br></span></font></div><div style="color:rgb(136,136,136)"><font size="2"><br></font></div><div style="color:rgb(136,136,136)"><font size="2"><a href="https://invoiced.com/" style="color:rgb(17,85,204)" target="_blank">Invoiced.com</a> | <a href="https://twitter.com/invoicedapp" style="color:rgb(17,85,204)" target="_blank">Twitter</a> | <a href="https://www.linkedin.com/companies/invoiced" style="color:rgb(17,85,204)" target="_blank">LinkedIn</a></font></div></div></div></div></div></div><br></div><br><div class="gmail_quote"><div dir="ltr" class="gmail_attr">On Wed, Feb 26, 2020 at 10:15 AM Bob Loblaw &lt;<a href="mailto:bob@example.com">bob@example.com</a>&gt; wrote:<br></div><blockquote class="gmail_quote" style="margin:0px 0px 0px 0.8ex;border-left:1px solid rgb(204,204,204);padding-left:1ex"><div dir="ltr">Body<div>hello</div><div>test</div><div>bye</div><div><br clear="all"><div><div dir="ltr"><div dir="ltr"><div><div dir="ltr"><div style="color:rgb(136,136,136)"><font size="2">Bob Loblaw<br></font></div><div style="color:rgb(136,136,136)"><i>Software Engineer<br></i></div><div style="color:rgb(136,136,136)"><i><img src="https://drive.google.com/a/invoiced.com/uc?id=1wNbxuyp-5fhNQBJRJFaOLk_QBOwuqZ5c&amp;export=download" width="96" height="24"><br></i></div><div style="color:rgb(136,136,136)"><font size="2">(a) 5301 Southwest Parkway, Suite 470, Austin, TX 78735</font></div><div><font size="2" style="color:rgb(136,136,136)">(p) </font><font color="#888888">(512) 270-3227</font></div><div style="color:rgb(136,136,136)"><font size="2">(e) <span style="color:rgb(56,118,29)"><font color="#1155cc"><a href="mailto:bob@example.com" target="_blank">bob@example.com</a></font><br></span></font></div><div style="color:rgb(136,136,136)"><font size="2"><br></font></div><div style="color:rgb(136,136,136)"><font size="2"><a href="https://invoiced.com/" style="color:rgb(17,85,204)" target="_blank">Invoiced.com</a> | <a href="https://twitter.com/invoicedapp" style="color:rgb(17,85,204)" target="_blank">Twitter</a> | <a href="https://www.linkedin.com/companies/invoiced" style="color:rgb(17,85,204)" target="_blank">LinkedIn</a></font></div></div></div></div></div></div></div></div>
                </blockquote></div>',
        ];

        $request = Request::create('/sendgrid/inbound', 'POST', $parameters);

        /** @var EmailThread $thread */
        $thread = EmailThread::where('name', 'SUBJECT')
            ->where('inbox_id', self::$inbox)
            ->oneOrNull();
        $thread->status = EmailThread::STATUS_CLOSED;
        $thread->saveOrFail();

        $this->getHandler()->processEmail($request);

        $thread->refresh();
        // should associate with an existing email thread
        $this->assertInstanceOf(EmailThread::class, $thread);
        $this->assertEquals(EmailThread::STATUS_OPEN, $thread->status);

        // should create an email
        /** @var InboxEmail $replyEmail */
        $replyEmail = InboxEmail::where('subject', 'Re: SUBJECT')
            ->where('thread_id', $thread)
            ->oneOrNull();

        /** @var InboxEmail $originalEmail */
        $originalEmail = InboxEmail::where('subject', 'SUBJECT')
            ->where('thread_id', $thread)
            ->oneOrNull();
        $this->assertInstanceOf(InboxEmail::class, $replyEmail);
        $this->assertEquals('<CAMNO8JyXN6b9YvE3pOjojdb1kbunZYOrbj8m7Hgx4a3Up4G5Ug@mail.gmail.com>', $replyEmail->message_id);
        $this->assertEquals($originalEmail->id(), $replyEmail->reply_to_email_id);

        // should associate to existing participants
        $from = $replyEmail->from;
        $this->assertEquals('bob@example.com', $from['email_address']);
        $this->assertEquals('Bob Loblaw', $from['name']);

        $toParticipants = $replyEmail->to[0];
        $this->assertEquals('abcdefghij@test.invoicedmail.com', $toParticipants['email_address']);

        $participants = EmailParticipant::where('email_address', 'bob@example.com')
            ->all();

        $this->assertCount(1, $participants);
    }

    /**
     * @depends testProcessInboxEmail
     */
    public function testProcessInd2638(): void
    {
        // First receive an email with an empty Message-ID
        $parameters = [
            'to' => 'abcdefghij@test.invoicedmail.com',
            'from' => 'Bob Loblaw <bob@example.com>',
            'subject' => 'Empty Message ID',
            'text' => '......',
            'headers' => <<<EOD
                From: Bob Loblaw <bob@example.com>
                Date: Wed, 26 Feb 2020 10:25:14 -0600
                Subject: Re: SUBJECT
                To: abcdefghij@test.invoicedmail.com"
                EOD,
            'html' => '......',
        ];
        $request = Request::create('/sendgrid/inbound', 'POST', $parameters);
        $this->getHandler()->processEmail($request);

        /** @var EmailThread $thread */
        $thread = EmailThread::where('name', 'Empty Message ID')
            ->where('inbox_id', self::$inbox)
            ->oneOrNull();

        // Now receive an email with an empty References header
        $parameters = [
            'to' => 'abcdefghij@test.invoicedmail.com',
            'from' => 'Bob Loblaw <bob@example.com>',
            'subject' => 'Empty References',
            'text' => '......',
            'headers' => <<<EOD
                From: Bob Loblaw <bob@example.com>
                Date: Wed, 26 Feb 2020 10:25:14 -0600
                Subject: Empty References
                Message-ID: <test-message-id@example.com>
                To: abcdefghij@test.invoicedmail.com"
                EOD,
            'html' => '......',
        ];
        $request = Request::create('/sendgrid/inbound', 'POST', $parameters);
        $this->getHandler()->processEmail($request);

        // The second email should NOT be added to the first email thread
        $this->assertEquals(1, InboxEmail::where('thread_id', $thread)->count());

        /** @var EmailThread $thread2 */
        $thread2 = EmailThread::where('name', 'Empty References')
            ->where('inbox_id', self::$inbox)
            ->oneOrNull();
        $this->assertEquals(1, InboxEmail::where('thread_id', $thread2)->count());
    }

    private function getHandler(): InboxEmailHandler
    {
        $handler = self::getService('test.inbox_email_handler');
        $handler->setStatsd(new StatsdClient());
        $handler->setInbox(self::$inbox);

        return $handler;
    }
}
