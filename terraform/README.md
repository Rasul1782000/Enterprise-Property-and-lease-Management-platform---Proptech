# Terraform: AWS infrastructure

This directory provisions the production AWS stack for the Enterprise Property
& Lease Management Portal:

- VPC with public, private, and database subnets, plus one NAT gateway per AZ.
- ECS Fargate cluster with four task definitions: web API, queue worker,
  scheduler, and a one-off migration task.
- Application Load Balancer with a regional ACM certificate and Route 53
  `api.<app_domain>` record.
- RDS for MySQL 8.4 (Multi-AZ by default) and ElastiCache Redis 7 with
  in-transit encryption.
- S3 buckets for documents, media, and ALB access logs.
- Secrets Manager entries for the Laravel `APP_KEY` and database password, plus
  optional OpenWA and SMTP secrets.
- CloudWatch logs, alarms, and dashboard.
- Optional CloudFront distribution in front of the Vercel frontend.

`main.tf` is intentionally a single-file stack. `bootstrap/main.tf` creates the
remote-state bucket and lock table that `main.tf` uses.

## Prerequisites

- Terraform 1.6 or newer (this stack was validated with 1.9.8).
- AWS CLI v2, authenticated with credentials that can create the resources in
  this stack.
- Docker, for building and pushing the backend image.
- An existing public Route 53 hosted zone for `app_domain`
  (`rasul17.indevs.in` by default). The zone must be delegated from the parent
  zone before ACM validation can succeed.

## 1. Bootstrap the remote state backend

Terraform cannot create the backend it is initialized against, so run the
bootstrap stack once. It uses local state; keep `terraform/bootstrap/terraform.tfstate`
safe and out of git (the repository `.gitignore` already handles this).

```powershell
cd terraform/bootstrap
terraform init
terraform plan
terraform apply
```

The defaults create:

- S3 bucket `gaarbejana-terraform-state` with versioning, SSE-S3, public access
  blocked, TLS-only access, and lifecycle cleanup for noncurrent versions.
- DynamoDB table `terraform-state-lock` with on-demand billing, PITR, and
  deletion protection.

The bucket name must be globally unique. If it is already taken, set
`state_bucket_name` / `lock_table_name` in a bootstrap `terraform.tfvars`, then
update the `backend "s3"` block in `../main.tf` to the same values before
initializing the main stack.

Both bootstrap resources have `prevent_destroy` and DynamoDB deletion
protection enabled. Remove those protections deliberately if you ever need to
tear the backend down.

## 2. Configure the main stack

From `terraform/`:

```powershell
Copy-Item terraform.tfvars.example terraform.tfvars
```

Edit `terraform.tfvars`. At minimum, review:

- `alarm_email` if you want CloudWatch alarm notifications.
- `app_key` (leave empty to generate a valid key once and store it in Secrets
  Manager).
- `openwa_api_key` and `mail_password` if those integrations are used. When
  empty, the corresponding secrets and task environment entries are omitted.
- `frontend_url` if the production frontend is not the default Vercel URL.
- `frontend_domain` / `frontend_origin` only if you enable CloudFront.

`terraform.tfvars` is gitignored because it can contain secrets.

## 3. Initialize and plan

```powershell
cd terraform
terraform init
terraform validate
terraform plan -out=tfplan
```

If you have an existing local `terraform.tfstate`, use
`terraform init -migrate-state` so Terraform copies it into the S3 backend.

Do not run a full apply yet if this is the first deployment: the ECS services
reference an image in the new ECR repository, and that image does not exist
until you build and push it.

## 4. First apply and push the backend image

Create just the ECR repository first:

```powershell
cd terraform
terraform apply -target=aws_ecr_repository.backend
$repo = terraform output -raw ecr_repository_url
$registry = $repo.Split("/")[0]

aws ecr get-login-password --region me-south-1 |
  docker login --username AWS --password-stdin $registry

# Run from the repository root, not from terraform/.
cd ..
docker build -f frontend/deployment/Dockerfile.backend -t "${repo}:latest" .
docker push "${repo}:latest"
```

The Docker build context must be the repository root because the Dockerfile
copies `backend/` and `frontend/deployment/backend-docker/`.

## 5. Apply the rest of the stack

```powershell
cd terraform
terraform apply
```

This creates the VPC, RDS, Redis, ALB/ACM certificates, secrets, ECS services,
and monitoring. RDS and ElastiCache can take 10-20 minutes; ACM validation can
take several more minutes after DNS is delegated.

If you skip the image push, ECS tasks will fail with an image pull error and the
service will not become healthy.

## 6. Run Laravel migrations

Migrations are deliberately not run automatically at container start. Use the
one-off `migrate` task definition created by this stack:

```powershell
cd terraform
$cluster = terraform output -raw ecs_cluster_name
$taskDef = terraform output -raw migration_task_definition_arn
$subnets = (terraform output -json private_subnet_ids | ConvertFrom-Json) -join ","
$securityGroup = terraform output -raw backend_security_group_id

$taskArn = aws ecs run-task `
  --cluster $cluster `
  --task-definition $taskDef `
  --launch-type FARGATE `
  --network-configuration "awsvpcConfiguration={subnets=[$subnets],securityGroups=[$securityGroup],assignPublicIp=DISABLED}" `
  --query "tasks[0].taskArn" `
  --output text

aws ecs wait tasks-stopped --cluster $cluster --tasks $taskArn
aws ecs describe-tasks --cluster $cluster --tasks $taskArn `
  --query "tasks[0].containers[0].exitCode" --output text
```

`0` means the migrations succeeded. Logs go to the group returned by:

```powershell
terraform output -raw backend_log_group_name
```

Run seeders only when you explicitly want to populate the database; they are
not part of the migration task.

## 7. DNS and certificates

The stack looks up the existing hosted zone with:

```hcl
data "aws_route53_zone" "main" {
  name = var.app_domain
}
```

If that zone does not exist, create it first and delegate the name servers at
the registrar/parent zone. After the stack is applied, print the name servers:

```powershell
terraform output -json route53_name_servers
```

The API is served at `https://api.<app_domain>`. ACM DNS validation records are
created automatically; validation will not complete until the zone is
authoritative and reachable.

### Root domain caveat

The apex record (`<app_domain>`) is **not** managed by default. This avoids a
conflict when the apex already points at Vercel or another provider. If you want
the apex to serve the ALB instead, set `manage_root_record = true` before
applying. The API record (`api.<app_domain>`) is independent of this choice.

## Optional CloudFront frontend

CloudFront is disabled by default. To put it in front of the Vercel frontend:

```hcl
enable_cloudfront = true
frontend_domain   = "app.rasul17.indevs.in"          # public alias
frontend_origin   = "frontend-gamma-hazel-80.vercel.app" # Vercel hostname, no scheme
```

The stack creates the ACM certificate in `us-east-1`, the distribution, and a
Route 53 alias for `frontend_domain`. The alias must be a subdomain of
`app_domain`; the apex is rejected by a lifecycle precondition because the apex
A record already points at the ALB. When `frontend_domain` is set, the backend
also uses `https://<frontend_domain>` for `FRONTEND_URL`; otherwise it uses
`frontend_url`.

If Vercel Deployment Protection is enabled, CloudFront requests will receive a
Vercel login page or 401. Disable protection for the production deployment or
use a public Vercel alias.

## Day-2 operations

### Deploy a new backend image

The default task definitions use the `:latest` tag. Pushing a new image does
not, by itself, make ECS start new tasks. Either force a new deployment:

```powershell
aws ecs update-service --cluster <cluster> --service <service> --force-new-deployment
```

or build with an immutable tag and apply it:

```powershell
docker build -f frontend/deployment/Dockerfile.backend -t "${repo}:2026-10-24" .
docker push "${repo}:2026-10-24"
cd terraform
terraform apply -var="backend_image=${repo}:2026-10-24"
```

Pinned tags are preferred because they make rollbacks and plan diffs
deterministic.

### Queue workers and scheduler

- `worker` runs `php artisan queue:work --sleep=3 --tries=3 --timeout=90`.
  Increase `worker_desired_count` if the Redis queue backs up.
- `scheduler` runs `php artisan schedule:work`. Keep
  `scheduler_desired_count = 1`; multiple schedulers can run the same scheduled
  command. The scheduler service is configured to replace its task instead of
  briefly running two during a deployment.
- Both use the same task size as the web service (`backend_cpu` /
  `backend_memory`). Adjust the task definitions if their resource needs differ.

### Secrets

`APP_KEY` is generated once with `random_id` when `var.app_key` is empty. It is
stored in Secrets Manager and injected into all backend task definitions.
Changing it invalidates existing encrypted sessions/data that depend on the old
key, so treat rotation as a deliberate operation.

Changing `openwa_api_key` or `mail_password` creates a new secret version. ECS
tasks read the secret at task start, so force a new deployment to pick it up.

### Logs and monitoring

All four task definitions write to the same CloudWatch log group with different
stream prefixes (`backend`, `worker`, `scheduler`, `migrate`):

```powershell
$logGroup = terraform output -raw backend_log_group_name
aws logs tail $logGroup --follow
```

The stack also creates an ALB target 5xx alarm, ECS/RDS alarms, and a dashboard.
Set `alarm_email` to subscribe an address to the SNS topic.

### Database access

RDS is in the private database subnets and is not publicly accessible. Use the
one-off ECS task pattern above (for example by overriding the container command
with `php artisan tinker` or a custom command) or add an SSM/bastion path if you
need interactive access.

Performance Insights is enabled automatically for instance classes that support
it. The smallest burstable classes (`db.t2.micro`, `db.t2.small`, `db.t3.micro`,
`db.t3.small`, `db.t4g.micro`, `db.t4g.small`) do not support it, so it is
disabled for them; this is why the default `db.t3.micro` still plans cleanly.

## Migrating an existing deployment

This configuration changed several resource names and defaults relative to the
original single-file stack:

- The backend now uses `gaarbejana-terraform-state` and `terraform-state-lock`.
  The old stack created `gaarbejana-terraform-state-production` and
  `terraform-state-lock-production`; those are not the backend resources. If
  you previously applied the old stack, review the plan carefully before
  letting Terraform destroy them.
- The RDS parameter group is now named `...-mysql84` with family `mysql8.4`.
  `allow_major_version_upgrade` is enabled so an existing 8.0 instance can be
  upgraded in place. Take a backup/snapshot first; major upgrades can take time.
- The ECS image now defaults to the ECR repository created by this stack. Set
  `backend_image` explicitly if you want to keep using an external registry.
- ALB logging now requires the dedicated log bucket policy and SSE-S3; the
  stack creates both.
- The ALB uses a regional ACM certificate; the existing `us-east-1` certificate
  remains only for CloudFront.
- The ALB target group is now named `...-tg`. The previous name could exceed
  the 32-character limit for environments such as `development`; if the old
  stack was applied, expect the target group to be replaced.
- The apex Route 53 record is now opt-in. If the old stack managed the apex,
  set `manage_root_record = true` to keep it; otherwise Terraform removes it so
  the apex can stay with Vercel.

If you have an existing local state file, back it up before running
`terraform init -migrate-state`.

## Destroying the stack

`terraform destroy` is intentionally guarded:

- RDS has deletion protection and a final snapshot in production.
- The ALB has deletion protection in production.
- The bootstrap state bucket and lock table have `prevent_destroy`.
- The S3 documents, media, and ALB log buckets are not force-destroyed.
- The ECR repository is not force-deleted; remove its images (or set
  `force_delete`) before destroying.

Disable those protections deliberately before destroying, and keep the final
RDS snapshot. Destroy the main stack before the bootstrap stack.

Secrets Manager retains deleted secret names for the configured recovery
window (30 days in production). Re-creating this stack with the same secret
names before that window expires will fail; purge the secrets or wait for the
window to end.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| `terraform init` cannot access the S3 backend | Bootstrap stack not applied, wrong bucket/table names, or wrong region. |
| ACM validation hangs | Route 53 zone not delegated, or the validation CNAMEs are not visible in the public zone. |
| ECS task stops with `CannotPullContainerError` | Image was not pushed, or the task execution role cannot pull from ECR. |
| ECS task stops with `ResourceInitializationError` | Secrets Manager access or CloudWatch Logs permission is missing; check the execution role policy. |
| ALB returns 502/503 | Container health check `/up` is failing, or the target group has no healthy tasks. Check the backend log group. |
| Laravel cannot connect to Redis | TLS is required. The stack sets `REDIS_HOST=tls://...`; do not override it with a plain hostname. |
| Laravel cannot read/write S3 | The task role (not static keys) grants S3 access; confirm the task definition uses `task_role_arn` and the bucket env vars. |
| CloudFront returns 401 | Vercel Deployment Protection is enabled for the origin deployment. |
