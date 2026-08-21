# Security

## Report a problem

Send a message to **it@codycloud.it**. Do not open a public issue for a
security problem.

Put this information in the message:

- The version of the plugin, of WordPress and of PHP.
- The steps to see the problem.
- What an attacker can do with it.

We answer in five working days.

## What the plugin protects

### The site key

The key can read and write the catalogue of the site. The key stays on the
server. The browser speaks to WordPress, and WordPress speaks to the service.

The key is never in a public page, never in a log, and never in an error
message. The panel shows the last four characters only.

### The base address

A user who can write the settings could send the key to another host. That
user could also use the server of the site to reach an internal service. For
this reason, `Impostazioni` accepts an address only when:

- the scheme is `https`, and
- the host is not `localhost` and not a loopback address, and
- the host, if it is already an IP address, is public.

The plugin does not resolve a domain name at the moment of the save. The
result of that resolution says nothing about the address at the moment of the
call, and it would give a false confidence.

### The public routes

The REST routes are public, because a customer is not a logged-in user. For
this reason, each route has a cost and a rate limit for each IP address.
Without the limit, the routes become a free way to consume the plan of the
site.

| Route | Cost |
|---|---|
| `/suggerimenti` | 0. It reads the database only. |
| `/ricerca` | 1 |
| `/evento` | 1 |
| `/immagine` | 4 |
| `/assistente` | 6 |

The limit is 40 for each 60 seconds, for each IP address.

The plugin uses `REMOTE_ADDR`. A client cannot select that value. Forwarded
headers come from the client: a client could send a different value at each
request and never meet the limit.

### The answer of the assistant

The text of the assistant comes from an external service. The plugin does not
trust it.

The plugin escapes the full text first. Then it turns on five marks on the
safe string. Then it passes the result through `wp_kses` with a closed list
of tags. A link stays a link only when it points to this site.

### The session of the funnel

The server writes the identifier, and the server reads it. The browser sends
nothing. If the browser sent the identifier, a person could take the orders of
another person.

The cookie is `HttpOnly`, `SameSite=Lax`, and `Secure` on HTTPS. The plugin
writes it at the first true interaction, not at the first page view. The
plugin asks the WP Consent API first, if the site has it.

### The statistics

A search box also gets things that are not searches. The plugin does not
write a search that has an email address or a long number. It counts it, so
that the total stays correct, but it does not keep the text.

## Versions with support

| Version | Support |
|---|---|
| 0.3.x | Yes |
| 0.2.x and older | No |
