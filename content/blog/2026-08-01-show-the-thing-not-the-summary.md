---
title: "Show the thing, not a summary of it"
description: "Three fixes from an invoicing app that all turned out to be the same fix: stop describing the data and put the data on the screen."
date: 2026-08-01
tags: [product, php, ux, invoicing]
draft: false
---

I spent a stretch of weeks on the invoice side of Kinvo, the billing app I build for people who do the kind of work that happens outside. Lawn crews, cleaners, handymen. The person using it is usually standing in a driveway with a phone in one hand.

Three separate things got fixed in that stretch, and I did not notice until the last one that they were all the same fix.

## One: the list had nine columns and told you nothing

The invoice list was a table with nine columns. Invoice number, customer, property, date, due date, amount, status, balance, actions. Every one of them defensible on its own. Together they were a wall.

The status column was a colored pill that said "Unpaid." The due date column, right next to it, said "Jul 3, 2026." Two columns, and between them they still made you do the arithmetic that actually mattered: is this late, and by how much?

The rebuild is four columns. Customer and property in one wide cell, with the invoice number and send date underneath in smaller text. Due date. Amount. Actions.

The status pill is gone. In its place, under the due date:

```php
$isOverdue = $invoice['balance_due'] > 0 && strtotime($invoice['due_date']) < strtotime('today');
$daysOverdue = $isOverdue ? (int) floor((strtotime('today') - strtotime($invoice['due_date'])) / 86400) : 0;
```

So the row says "12 days late" in red, or "Paid" in green, or nothing at all if it is simply not due yet. "Unpaid" was a category. "12 days late" is a fact, and it is the fact that decides whether you pick up the phone.

The amount column got the same treatment. It used to show the invoice total, with a separate balance column next to it. Now one number, and it is the one you care about: what is still owed. If a partial payment has landed, a second line underneath reads "of $840.00" so the total is still there without taking a column to say so.

Nine columns to four, and the row is more informative than it was. Removing the summary made room for the substance.

## Two: the numbers at the top were only true by accident

The same page had three summary cards up top: total invoices, total billed, total paid. They were computed like this:

```php
$totalInvoices = count($invoices);
$totalAmount = array_sum(array_column($invoices, 'total'));
$totalPaid = array_sum(array_column($invoices, 'total_paid'));
```

Summing the rows that happened to be in memory. Which was fine, because the query had no `LIMIT` and every invoice ever created was in memory. That is also why the page got slower every month, and why the loop underneath then went back to the database for each row: a query to sum that invoice's payments, a lookup for its property, and then a call to a status helper that summed the same payments a second time. Roughly three hundred round trips for a hundred invoices, before a single pixel was drawn.

Adding pagination fixes the speed. It also quietly breaks those three cards, because "the rows in memory" stops meaning "all of them." So the summary had to move to where the truth is:

```sql
SELECT COUNT(*) AS n,
       COALESCE(SUM(i.total), 0) AS total_amount,
       COALESCE(SUM(COALESCE(paid.total_paid, 0)), 0) AS total_paid
FROM invoices i
JOIN customers c ON i.customer_id = c.id
LEFT JOIN (
    SELECT invoice_id, SUM(amount) AS total_paid
    FROM payments
    GROUP BY invoice_id
) paid ON paid.invoice_id = i.id
WHERE $whereSql
```

That grouped subquery does double duty. It feeds the summary, and joined into the list query it kills the per-row payment lookups. Same for the property join. Twenty five rows a page, three queries total, and the cards report on every matching invoice rather than on whatever the page happened to render.

The part worth keeping: a derived number that agrees with the screen is not the same as a number that is correct. Mine agreed with the screen for a year, right up until the screen stopped showing everything.

## Three: the shortcut nobody pressed

Most of this work is repeat work. The same lawn, every other Tuesday, the same three line items. So I built a button. Pick a customer, and if they have a previous invoice, a panel appears offering to copy its line items into the one you are starting.

The panel said this:

> The last invoice for this property was Jul 18, 2026. 3 lines, $240.00.
>
> [Copy those line items]

It worked. It saved a real amount of typing. And it went unused, because "3 lines, $240.00" asks you to take a leap. Three lines of what? Was that the visit where you also hauled off the branch pile? Copying is fast, but checking what you copied means saving the invoice and reading it back, and that is slower than just typing the three lines you already know by heart.

The fix was not a better sentence. It was deleting the sentence and rendering the actual items:

> The last invoice for this property was Jul 18, 2026. Here is what was on it:
>
> | | |
> |---|---|
> | Mowing and edging | 1 × $95.00 = $95.00 |
> | Hedge trimming, front bed | 2 × $60.00 = $120.00 |
> | Debris haul-off | 1 × $25.00 = $25.00 |
> | **Total** | **$240.00** |
>
> [Copy those line items]

Same endpoint, same data, one more render pass. The count was a description of the line items. The line items were right there the whole time, already in the JSON response, being reduced to the number three.

## The pattern

A status pill instead of the days. A sum of what loaded instead of a sum of what exists. A count of the lines instead of the lines.

Every one of them was a summary standing in front of the thing it summarized, and in every case the thing itself was already loaded, already paid for, already one render away. Summarizing felt like design. Restraint, hierarchy, not overwhelming the user. What it actually did was move work off my side of the screen and onto theirs.

The test I use now, when I catch myself writing a label that counts or classifies something: is the real data available at this exact point in the code? If it is, showing it is almost always cheaper than describing it, and it is the version the person in the driveway can act on without a second tap.
