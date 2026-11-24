# EnoxSuite GitHub Actions Deployment Documentation

**Project:** `enorsia/enox_erp`\
**Server directory:** `/srv/enox_erp`\
**Deployment branch:** `master`\
**Workflow file:** `.github/workflows/action.yml`\
**Deployment script:** `deploy.sh`

> **Current status (24 November 2026):** The server-side SSH service is
> running and listening on `0.0.0.0:22` and `[::]:22`. GitHub Actions
> currently reports `dial tcp <server>:22: i/o timeout`. This is a
> network-connectivity problem until proven otherwise; it occurs before
> SSH key authentication. The external firewall/NAT path is still being
> investigated. Do not treat the deployment as fully operational until a
> GitHub Actions run succeeds.

------------------------------------------------------------------------

## 1. Deployment architecture

The deployment has two separate SSH connections. They serve different
purposes and must not be confused.

1.  **Server → GitHub:** The server uses its own SSH key to fetch the
    repository.
2.  **GitHub Actions runner → Server:** GitHub Actions uses a private
    key stored in a GitHub Actions secret to connect to the server and
    execute `deploy.sh`.

The intended flow is:

``` text
Developer pushes to master
          |
          v
GitHub Actions workflow starts
          |
          v
Read commit message deployment flags
          |
          v
Connect to server over SSH (TCP port 22)
          |
          v
cd /srv/enox_erp
          |
          v
Run deploy.sh with selected flags
          |
          v
Fetch latest master and reset working tree
          |
          v
Run selected deployment tasks
          |
          v
Deployment result appears in GitHub Actions
```

## 2. Prerequisites

Before configuring deployment, confirm that:

-   The application is already deployed and works on the server.
-   The server has Git, PHP, Composer, Node.js/npm (if frontend assets
    are built there), and the required PHP extensions.
-   The repository is accessible from the server.
-   The deployment account can access `/srv/enox_erp`.
-   The application environment file and production/staging
    configuration already exist on the server.
-   Required database backups and recovery procedures are handled
    according to the team's normal release process.

Do not put `.env`, database passwords, API keys, private SSH keys, or
other credentials in Git.

## 3. Confirm the server repository

Log in to the server using the deployment account:

``` bash
ssh ukenorsia@YOUR_SERVER_HOST
```

Go to the project directory:

``` bash
cd /srv/enox_erp
```

Check the current branch and working tree:

``` bash
git branch --show-current
git status
```

The expected branch is `master`. The working tree should be clean before
deployment.

Check that the server can reach GitHub:

``` bash
git fetch origin
```

The server's GitHub SSH key is separate from the key used by GitHub
Actions to connect to the server. A successful `git fetch origin`
confirms the server-to-GitHub direction works; it does not prove that
GitHub Actions can connect inbound to the server.

## 4. Configure server-to-GitHub SSH access

This key allows the server to fetch the private repository.

1.  Log in as the account that performs deployment (`ukenorsia`).

2.  Check whether a dedicated key already exists:

    ``` bash
    ls -la ~/.ssh
    ```

3.  If a key is needed, generate a dedicated key. Do not overwrite an
    existing key:

    ``` bash
    ssh-keygen -t ed25519 -C "enox-erp-server-to-github"
    ```

    Use a distinct filename if the default key already exists, for
    example `~/.ssh/id_ed25519_github`.

4.  Add the **public** key (`.pub` file) to the GitHub repository's
    **Settings → Deploy keys**. Grant read-only access unless the server
    genuinely needs to push.

5.  Configure the SSH client to use that key for GitHub, if necessary.
    Example:

    ``` sshconfig
    Host github.com
        HostName github.com
        User git
        IdentityFile ~/.ssh/id_ed25519_github
        IdentitiesOnly yes
    ```

6.  Test the connection:

    ``` bash
    ssh -T git@github.com
    git -C /srv/enox_erp fetch origin
    ```

Use the actual key filename configured on the server. Never copy the
private key into the repository or a GitHub Deploy Key field.

## 5. Configure GitHub Actions-to-server SSH access

This is a separate key pair. It allows the GitHub-hosted runner to log
in to the server.

### 5.1 Generate a dedicated deployment key

Generate the key securely on a trusted administrative machine. Do not
overwrite an existing key:

``` bash
ssh-keygen -t ed25519 -C "github-actions-enox-erp-deploy"
```

Use a dedicated filename, for example `enox_erp_actions`. For unattended
CI, the key is commonly created without a passphrase; protect the
private key as a secret.

### 5.2 Install the public key for the deployment account

Add the contents of `enox_erp_actions.pub` to:

``` text
/home/ukenorsia/.ssh/authorized_keys
```

Ensure the SSH directory and file permissions are appropriate:

``` bash
chmod 700 /home/ukenorsia/.ssh
chmod 600 /home/ukenorsia/.ssh/authorized_keys
```

Confirm the file is owned by `ukenorsia`:

``` bash
chown -R ukenorsia:ukenorsia /home/ukenorsia/.ssh
```

Do not replace the existing `authorized_keys` file. Append the new
public key so existing access is preserved.

### 5.3 Add GitHub repository secrets

In GitHub, open:

**Repository → Settings → Secrets and variables → Actions → New
repository secret**

Create these secrets:

  -----------------------------------------------------------------------
  Secret                              Value
  ----------------------------------- -----------------------------------
  `SERVER_HOST`                       The server's real inbound-reachable
                                      public IP address or DNS hostname

  `SERVER_USER`                       `ukenorsia`

  `SERVER_SSH_KEY`                    Complete contents of the private
                                      key, including BEGIN/END lines
  -----------------------------------------------------------------------

Do not use the server's private LAN address as `SERVER_HOST` for a
GitHub-hosted runner unless the runner has a route into that private
network.

**Important:** An IP returned by an outbound command such as
`curl -4 ifconfig.me` is the server's public egress IP. It does not, by
itself, prove that the address is assigned to the server for inbound
connections or that TCP port 22 is forwarded to it. Confirm the inbound
address and port-forwarding arrangement with the hosting/network
administrator.

## 6. Verify SSH network reachability before running Actions

A GitHub Actions error such as:

``` text
dial tcp <SERVER_HOST>:22: i/o timeout
```

means the runner did not establish a TCP connection to port 22. This
happens before SSH key authentication. Changing the private key will not
fix a network timeout.

Run these checks on the server.

### 6.1 Check the SSH service

``` bash
sudo systemctl status ssh
```

Expected: `Active: active (running)`.

On some Linux distributions the service may be named `sshd`:

``` bash
sudo systemctl status sshd
```

### 6.2 Check the listening socket

``` bash
sudo ss -tlnp | grep ':22'
```

A listener such as the following means SSH is listening on all IPv4
interfaces:

``` text
0.0.0.0:22
```

And this indicates all IPv6 interfaces:

``` text
[::]:22
```

A listener only on `127.0.0.1:22` would not accept remote IPv4
connections.

### 6.3 Check the host firewall

If UFW is installed:

``` bash
sudo ufw status verbose
```

Review the rules before changing anything. Do not disable the firewall
as a troubleshooting shortcut. If an inbound rule is required, restrict
access to trusted source IP ranges where practical and follow the
hosting provider's security policy.

If UFW is inactive or does not explain the timeout, check the provider
firewall/security group, edge firewall, router, and NAT/port-forwarding
configuration.

### 6.4 Check NAT and provider firewall

Ask the hosting/network administrator to verify:

-   Which public IP is assigned for inbound access to this server.
-   Whether TCP port 22 is allowed by the provider firewall/security
    group.
-   Whether a router or NAT gateway is in front of the server.
-   If NAT is used, whether external TCP port 22 is forwarded to
    `172.16.61.171:22` (or the server's current private IP).
-   Whether any upstream firewall or access-control list blocks
    GitHub-hosted runner traffic.

Do not assume that the public egress IP and inbound destination IP are
the same.

### 6.5 Test from outside the server

From a machine outside the server's network, test the actual inbound
host:

``` bash
ssh -vvv ukenorsia@YOUR_SERVER_HOST
```

Or test TCP connectivity:

``` bash
nc -vz YOUR_SERVER_HOST 22
```

A successful TCP connection confirms the port is reachable from that
test network, but the GitHub-hosted runner may use a different source
IP. If local testing succeeds but Actions still times out, investigate
provider rules and GitHub runner source-IP restrictions.

Only after TCP connectivity works should you troubleshoot SSH keys,
usernames, and `authorized_keys`.

## 7. GitHub Actions workflow

The workflow is stored at:

``` text
.github/workflows/action.yml
```

The configured workflow is triggered by pushes to `master`. It reads
deployment flags from the latest commit message and connects to the
server using `appleboy/ssh-action`.

The workflow uses these secrets:

-   `SERVER_HOST`
-   `SERVER_USER`
-   `SERVER_SSH_KEY`

The remote command is:

``` bash
cd /srv/enox_erp
chmod +x deploy.sh
./deploy.sh <deployment-flags>
```

Review the actual committed workflow before changing it. The description
above records the current setup; it is not a substitute for the exact
YAML in the repository.

### Important workflow considerations

-   The current workflow reads the head commit message. Put the
    deployment flags in the commit that triggers the workflow.
-   If the workflow requires `[deploy]`, a commit without that marker
    may skip the SSH deployment step.
-   The current fallback behavior may use `--fast` when no task flags
    are detected. Confirm this behavior in the actual workflow before
    relying on it.
-   A commit message is data passed into the workflow. Avoid directly
    interpolating untrusted commit-message text into shell code. Prefer
    passing it through an environment variable and parsing it safely.
-   Pin third-party GitHub Actions to reviewed versions or immutable
    commit SHAs according to the team's security policy.

## 8. Deployment flags

The `deploy.sh` script supports these flags:

  -----------------------------------------------------------------------
  Flag                                Intended task
  ----------------------------------- -----------------------------------
  `--composer=yes`                    Install/update PHP Composer
                                      dependencies

  `--npm=yes`                         Install/update Node.js dependencies

  `--assets=yes`                      Build frontend assets

  `--migrate=yes`                     Run the migration script
                                      (`run_migrations.sh`)

  `--supervisor=yes`                  Restart configured Supervisor
                                      workers

  `--fast`                            Run the script's fast deployment
                                      path; verify its exact skipped
                                      tasks in the script
  -----------------------------------------------------------------------

The workflow's commit-message parser currently uses markers such as:

``` text
[deploy]
[composer]
[npm]
[assets]
[migration]
[supervisor]
```

Confirm the parser-to-script mapping in `.github/workflows/action.yml`.
In particular, the workflow marker `[migration]` maps to the script
argument `--migrate=yes`.

Example commit message:

``` text
[deploy] [composer] [assets] [migration] Deploy order module update
```

This example requests deployment, Composer work, asset build, and
migrations, provided the workflow parser maps those markers as expected.

Another example:

``` text
[deploy] [supervisor] Restart queue workers
```

Use only the flags required for the release. Migrations and dependency
installation can affect a live application; review the change and
maintenance requirements before requesting them.

## 9. What deploy.sh does

The current deployment script is located at:

``` text
/srv/enox_erp/deploy.sh
```

Its recorded behavior includes:

1.  Parse deployment flags.
2.  Fetch the latest repository state from `origin`.
3.  Check out `master`.
4.  Reset the working tree to `origin/master`.
5.  Run Composer tasks when requested.
6.  Run npm tasks and build assets when requested.
7.  Run database migrations through `bash run_migrations.sh` when
    requested.
8.  Apply configured file permissions.
9.  Clear or rebuild Laravel caches as configured.
10. Restart Supervisor programs when requested:
    -   `enox_suite_queue:*`
    -   `tracking-database-worker:*`

Because the script uses `git reset --hard origin/master`, any
uncommitted tracked changes in the deployment directory can be
discarded. Keep server-specific configuration such as `.env` out of Git
and do not make manual tracked-code edits on the server.

Before changing the script, inspect it:

``` bash
sed -n '1,260p' deploy.sh
```

Check its syntax:

``` bash
bash -n deploy.sh
```

Do not run a production deployment merely to test syntax.

## 10. Manual deployment procedure

If an approved deployment must be performed manually while GitHub
Actions connectivity is being fixed, use the team's release procedure
and the appropriate flags.

First inspect the repository:

``` bash
cd /srv/enox_erp
git status
git fetch origin
git log -1 --oneline origin/master
```

Confirm the target commit with the release owner. Then run the approved
deployment command, for example:

``` bash
chmod +x deploy.sh
./deploy.sh --assets=yes
```

This is only an example. Do not include migrations, Composer, npm, or
Supervisor flags unless they are required and approved for that release.

After deployment, verify the application, logs, queue workers, and any
affected integration.

## 11. Validate a GitHub Actions deployment

After inbound SSH connectivity is confirmed:

1.  Push an approved test commit to `master` with `[deploy]` and the
    required task markers.

2.  Open the repository's **Actions** tab.

3.  Select the triggered workflow run.

4.  Confirm the workflow parsed the expected flags.

5.  Confirm the SSH action connected to the expected host and user.

6.  Confirm the remote command entered `/srv/enox_erp`.

7.  Review the output from `deploy.sh`.

8.  Confirm the workflow completes successfully.

9.  Verify the deployed commit on the server:

    ``` bash
    cd /srv/enox_erp
    git rev-parse --short HEAD
    git status --short
    ```

10. Perform application smoke tests and check the relevant logs and
    Supervisor processes.

A green GitHub Actions run is not enough on its own; verify the
application behavior after deployment.

## 12. Troubleshooting guide

  ---------------------------------------------------------------------------------
  Error or symptom                  Meaning                 Next check
  --------------------------------- ----------------------- -----------------------
  `dial tcp ...:22: i/o timeout`    TCP connection to SSH   Inbound IP, provider
                                    did not complete        firewall, NAT, port
                                                            forwarding, routing

  `connection refused`              Host was reached but    SSH service and
                                    port was not accepting  listening socket
                                    connections             

  `Permission denied (publickey)`   TCP/SSH connection      `SERVER_USER`, private
                                    works, but key          key secret, matching
                                    authentication failed   public key in
                                                            `authorized_keys`, file
                                                            permissions

  `Host key verification failed`    SSH host identity check Verify server host key
                                    failed                  securely and configure
                                                            the action's host-key
                                                            handling

  `Permission denied` during        SSH worked, but the     Directory ownership,
  deployment                        remote account lacks a  sudo policy, deployment
                                    required permission     script permissions

  `git fetch` authentication        Server cannot           Server-to-GitHub key,
  failure on server                 authenticate to GitHub  Deploy Key, SSH config

  Composer/npm/build failure        Deployment reached the  Runtime versions,
                                    script but a            lockfiles, network,
                                    dependency/build step   package scripts,
                                    failed                  permissions

  Migration failure                 Database migration step Database connection,
                                    failed                  migration output,
                                                            schema state; follow
                                                            recovery procedure

  Supervisor restart failure        Application deployment  Supervisor program
                                    may have completed but  names, permissions,
                                    worker restart did not  process status
  ---------------------------------------------------------------------------------

Useful checks:

``` bash
sudo systemctl status ssh
sudo ss -tlnp | grep ':22'
sudo ufw status verbose
sudo supervisorctl status
```

For Laravel logs, use the application's configured log location and
ensure permissions are correct. Avoid printing secrets or environment
variables into Actions logs.

## 13. Security and operational safeguards

-   Use separate SSH keys for server-to-GitHub and
    GitHub-Actions-to-server access.
-   Use a dedicated deployment account; do not use `root` for CI
    deployment.
-   Store private keys only in GitHub Actions secrets or an approved
    secret manager.
-   Never commit private keys, `.env`, credentials, or production data.
-   Restrict inbound SSH access according to the organization's network
    policy.
-   Do not disable the firewall to make deployment work.
-   Do not change SSH authentication settings without an approved access
    plan and a second working administrative session.
-   Keep the deployment directory's ownership and writable runtime
    directories intentional.
-   Do not run `git reset --hard` if there are unreviewed server-side
    changes.
-   Review migrations before running them; use backward-compatible
    migrations where possible.
-   Keep a tested database backup and application recovery plan for
    releases that change data or schema.
-   Review third-party GitHub Actions and pin versions according to
    security policy.
-   Do not expose full SSH debug logs, private keys, tokens, or
    sensitive server details in public issue reports.

## 14. Current investigation checklist

As of 24 November 2026, these checks have been completed:

-   [x] `ssh.service` is active and running.
-   [x] SSH is listening on `0.0.0.0:22`.
-   [x] SSH is listening on `[::]:22`.
-   [x] Server-to-GitHub `git fetch origin` has worked.
-   [x] Server repository was updated to `origin/master` and the working
    tree was clean at the time of the check.
-   [ ] Review `sudo ufw status verbose`.
-   [ ] Confirm the actual inbound public IP/hostname with the hosting
    provider.
-   [ ] Confirm provider firewall/security-group rules for inbound TCP
    port 22.
-   [ ] Confirm NAT/port forwarding if the server is behind a private
    network.
-   [ ] Test inbound SSH from an external network.
-   [ ] Re-run GitHub Actions and confirm the SSH action connects.
-   [ ] Confirm a complete deployment and application smoke test.

**Current GitHub Actions error:**

``` text
dial tcp <SERVER_HOST>:22: i/o timeout
```

**Current conclusion:** The SSH daemon is running and listening
correctly on the server. The next investigation should focus on the
inbound network path (host firewall, provider firewall, public IP
mapping, NAT, and routing). Do not rotate SSH keys or weaken firewall
settings unless later evidence points to an authentication or
access-control issue.

------------------------------------------------------------------------

## 15. Release completion checklist

Use this checklist for each deployment:

-   [ ] Changes reviewed and approved.
-   [ ] Correct commit pushed to `master`.
-   [ ] Commit message contains `[deploy]` and only the required task
    markers.
-   [ ] Workflow run started as expected.
-   [ ] SSH connection succeeded.
-   [ ] Correct server and project directory confirmed.
-   [ ] Deployment script completed without errors.
-   [ ] Migrations completed, if requested.
-   [ ] Assets built and available, if requested.
-   [ ] Supervisor workers healthy, if restarted.
-   [ ] Application smoke tests passed.
-   [ ] Relevant logs reviewed.
-   [ ] Release owner/team notified.
