# 0003. Subscription billing provider: Stripe Billing

- Status: Accepted
- Date: 2026-10-27
- Deciders: Bambie Murphy

## Context

Charities subscribe to NPO.WorkDash on monthly or yearly plans with trials, plan changes, coupons and UK VAT invoices (milestone M7). We need hosted checkout and a customer portal so we never handle card data directly, webhooks to keep plan state in sync, and dunning for failed payments.

This decision covers **our own subscription billing only**. Donations that charities take from their supporters, and Direct Debit records in the fundraising module, are separate concerns.

## Decision

Use **Stripe Billing** through Laravel Cashier (Stripe):

- Stripe Checkout for sign-up and Stripe's customer portal for card and plan management.
- Stripe Tax or tax rates for UK VAT, with VAT-compliant invoices from Stripe.
- Webhooks (signature-verified) as the source of truth for subscription state.
- Smart Retries and Stripe's dunning emails for failed payments.

## Alternatives considered

- **Paddle (merchant of record)**: Paddle handles VAT and acts as the seller, but it takes a higher fee, offers less control over the checkout and invoices, and has weaker Laravel support than Cashier.
- **GoCardless**: excellent for UK Direct Debit and lower fees, but card payments, a hosted portal and dunning would all need building ourselves. It may be added later as a payment method.
- **Building our own billing on a payment gateway**: high effort and risk (proration, dunning, VAT), and no benefit at this stage.

## Consequences

- PCI scope stays minimal because card data never touches our servers.
- Stripe keys and the webhook secret are secrets that must be handled as described in the secrets documentation.
- The webhook endpoint must be reachable from Stripe on each Cloudron environment, with separate test-mode keys for staging.
- We depend on Stripe's pricing and availability, and plan and price IDs must be kept in sync between Stripe and the app's plan table.
