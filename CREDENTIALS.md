# Jenkins credentials used by this pipeline

The pipeline references credentials by ID. Create them once under
**Manage Jenkins → Credentials → System → Global credentials
(unrestricted) → Add Credentials**.

## `vercel-token` — required for Vercel deploys

The **Deploy Frontend to Vercel** stage (`Jenkinsfile`, `main` only)
uses a Vercel access token. It is referenced as `VERCEL_TOKEN_ID` in
the `Jenkinsfile`.

**Create the Vercel token**

1. Vercel → your avatar → **Settings** → **Tokens** → **Create Token**.
2. Name it `jenkins-ci` and keep the team `rasul-ahmed-khans-projects`.
3. Copy the token — it is shown only once.

**Add it to Jenkins**

1. **Manage Jenkins → Credentials → System → Global credentials
   (unrestricted) → Add Credentials**.
2. Kind: **Secret text**.
3. Secret: the token you copied.
4. ID: **`vercel-token`**  ← must match `VERCEL_TOKEN_ID` in `Jenkinsfile`.
5. Description (optional): `Vercel token for the frontend pipeline`.
6. Click **Create**.

> Until this credential exists the pipeline still passes: the deploy
> stage reports **UNSTABLE** and prints what to do instead of failing
> the build. Once it is added, every push to `main` deploys to Vercel
> automatically (the multibranch job also re-scans every 5 minutes).

## `docker-registry-credentials` — only for the optional image push

Used only when a build is run with **Push images to registry** checked
(the `PUSH_IMAGE` parameter). It is a **Username with password**
credential:

- Username: registry user
- Password: registry token/password
- ID: **`docker-registry-credentials`**  ← must match `CREDENTIALS_ID`
  in `Jenkinsfile`.

This stage pushes images to a registry. It is **not** a deployment.
