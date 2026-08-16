# Email on the VPS — send path built, DNS records still needed

A send-only mail server now runs on the VPS, so Paymenter can send without an external SMTP
provider. Three DNS records are needed before that mail will reach inboxes, and a fourth if
tickets should be answerable by email.

---

## What is running

| Piece | State |
|---|---|
| **Postfix** | send-only relay, listening on `127.0.0.1:25` and `172.18.0.1:25` (docker bridge) |
| **OpenDKIM** | signing every outgoing message, selector `mail`, 2048-bit |
| **Paymenter** | `mail_host = 172.18.0.1`, port 25, no auth, from `noreply@paymenter-dev.7hoop.net` |
| Outbound port 25 | open — Gmail accepted our HELO, MAIL FROM and RCPT TO (all `250`) |
| Relay scope | only `127.0.0.0/8` and `172.18.0.0/16`; it is **not** an open relay |

Verified: a message sent from the application is handed to the relay and leaves carrying a
`DKIM-Signature` header.

---

## DNS records to publish (Cloudflare — DNS only, not proxied)

### 1. SPF — authorises this server to send for the domain

```
Type: TXT
Name: paymenter-dev
Value: v=spf1 ip4:69.197.186.115 ip6:2001:4858:aaaa:164:be24:11ff:fee8:9851 ~all
```

The server reaches Gmail over IPv6, so the IPv6 address matters as much as the IPv4 one —
omitting it is a common reason SPF passes in testing and fails in production.

### 2. DKIM — proves the message was not altered

```
Type: TXT
Name: mail._domainkey.paymenter-dev
Value: v=DKIM1;h=sha256;k=rsa;p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArK0M3/tSEYGrhIGHALh4RyNQOrxrQBgEsVCm1sYKXm3UqcuA8FbjmlI2xZkQo1x0dKkP2n7qGuGlz9+En4Pg8OOLrNHO7peuipIjyEMcSqOTfHZESuDSE+dGwc+UFsWUmqHN1UUgrORPte3kkHh+A5u7g2RN1zSY5DSTtAJVExp/BCPcUDP+uPJDFsjCBjLObqMIHeOsyuvGqYWwFvIGC7qtHar3NI87SdJ9aoIVKvbq/W2mhf2YIARW1eNVIPWdeBLNGahYSD4W6zQzKhNO/kjY5q3Opb0Kgf2CVQk8u/on5kpXgkOhbUUhBSZyWluq0QYA/zq/P/jXoTv9cO9dQwIDAQAB
```

### 3. DMARC — tells receivers what to do when the above fail

```
Type: TXT
Name: _dmarc.paymenter-dev
Value: v=DMARC1; p=none; rua=mailto:postmaster@paymenter-dev.7hoop.net
```

`p=none` reports without rejecting; tighten to `quarantine` once reports look clean.

### 4. MX — only if tickets should be answerable by email

```
Type: MX
Name: paymenter-dev
Value: paymenter-dev.7hoop.net    Priority: 10
```

Paymenter already ships email-to-ticket (`app:fetch-emails`, scheduled every five minutes,
using `directorytree/imapengine`). It needs an IMAP mailbox and these settings:
`ticket_mail_piping`, `ticket_mail_host`, `ticket_mail_port`, `ticket_mail_email`,
`ticket_mail_password`. **Receiving is not configured yet** — Postfix here is send-only, and
an inbound mailbox plus Dovecot would be the next step once the MX record exists.

---

## Honest limitation: reverse DNS

The IP's PTR record is `I.` / `I.local.` rather than a hostname:

```
115.186.197.69.in-addr.arpa  ->  I.
```

Many receivers penalise or reject mail from an IP whose reverse DNS does not resolve to a
sensible FQDN. **Only the hosting provider can set this** — it is not a DNS record we can
publish. Ask them to set it to `paymenter-dev.7hoop.net`.

Until PTR is fixed and the records above are published, expect mail to be spam-foldered by
Gmail and Outlook. Sending works; *delivery to the inbox* is what these records buy.

---

## If deliverability matters more than self-hosting

For a billing system, password resets and invoices landing in spam is a real problem. A
transactional provider (Postmark, Mailgun, SendGrid — all have free tiers) sidesteps PTR and
IP reputation entirely; it still needs SPF and DKIM records, but they are the provider's and
come with instructions. Point `mail_host`/`mail_username`/`mail_password` at it and the
Postfix relay becomes unnecessary.

---

## Re-checking after DNS is published

```
dig +short TXT paymenter-dev.7hoop.net
dig +short TXT mail._domainkey.paymenter-dev.7hoop.net
dig +short TXT _dmarc.paymenter-dev.7hoop.net
```

Then send a test from the application and confirm the receiving side reports
`spf=pass` and `dkim=pass` in the message headers.
