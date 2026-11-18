###############################################################################
# Terraform remote-state bootstrap
#
# Creates the S3 bucket and DynamoDB table referenced by the `backend "s3"`
# block in ../main.tf. Terraform cannot create the backend it is initialized
# against, so this stack intentionally uses local state.
#
# Run this once per AWS account/region. Keep the local terraform.tfstate file
# safe; it is ignored by git. See ../README.md for the full procedure.
###############################################################################

terraform {
  required_version = ">= 1.6.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.80"
    }
  }
}

provider "aws" {
  region = var.aws_region

  default_tags {
    tags = {
      Project   = var.project_name
      ManagedBy = "terraform"
      Purpose   = "terraform-state"
    }
  }
}

# ─── Variables ────────────────────────────────────────────────────────────────

variable "aws_region" {
  description = "AWS region for the state bucket and lock table"
  type        = string
  default     = "me-south-1"
}

variable "project_name" {
  description = "Project name used for tagging"
  type        = string
  default     = "gaarbejana"
}

variable "state_bucket_name" {
  description = "Globally unique S3 bucket for Terraform state. Must match the backend block in ../main.tf."
  type        = string
  default     = "gaarbejana-terraform-state"
}

variable "lock_table_name" {
  description = "DynamoDB table used for Terraform state locking. Must match the backend block in ../main.tf."
  type        = string
  default     = "terraform-state-lock"
}

# ─── State bucket ─────────────────────────────────────────────────────────────

resource "aws_s3_bucket" "state" {
  bucket = var.state_bucket_name

  tags = {
    Name = var.state_bucket_name
  }

  lifecycle {
    prevent_destroy = true
  }
}

resource "aws_s3_bucket_versioning" "state" {
  bucket = aws_s3_bucket.state.id

  versioning_configuration {
    status = "Enabled"
  }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "state" {
  bucket = aws_s3_bucket.state.id

  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
    bucket_key_enabled = true
  }
}

resource "aws_s3_bucket_public_access_block" "state" {
  bucket = aws_s3_bucket.state.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

resource "aws_s3_bucket_ownership_controls" "state" {
  bucket = aws_s3_bucket.state.id

  rule {
    object_ownership = "BucketOwnerEnforced"
  }
}

resource "aws_s3_bucket_policy" "state" {
  bucket = aws_s3_bucket.state.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Sid       = "DenyInsecureTransport"
        Effect    = "Deny"
        Principal = "*"
        Action    = "s3:*"
        Resource = [
          aws_s3_bucket.state.arn,
          "${aws_s3_bucket.state.arn}/*",
        ]
        Condition = {
          Bool = {
            "aws:SecureTransport" = "false"
          }
        }
      }
    ]
  })

  depends_on = [aws_s3_bucket_public_access_block.state]
}

resource "aws_s3_bucket_lifecycle_configuration" "state" {
  bucket = aws_s3_bucket.state.id

  rule {
    id     = "expire-old-state-versions"
    status = "Enabled"

    filter {}

    noncurrent_version_expiration {
      noncurrent_days = 90
    }

    abort_incomplete_multipart_upload {
      days_after_initiation = 7
    }
  }
}

# ─── State lock table ────────────────────────────────────────────────────────

resource "aws_dynamodb_table" "locks" {
  name         = var.lock_table_name
  billing_mode = "PAY_PER_REQUEST"
  hash_key     = "LockID"

  attribute {
    name = "LockID"
    type = "S"
  }

  point_in_time_recovery {
    enabled = true
  }

  server_side_encryption {
    enabled = true
  }

  deletion_protection_enabled = true

  tags = {
    Name = var.lock_table_name
  }

  lifecycle {
    prevent_destroy = true
  }
}

# ─── Outputs ─────────────────────────────────────────────────────────────────

output "state_bucket_name" {
  description = "S3 bucket name for the Terraform backend"
  value       = aws_s3_bucket.state.id
}

output "lock_table_name" {
  description = "DynamoDB table name for Terraform state locking"
  value       = aws_dynamodb_table.locks.name
}

output "backend_config" {
  description = "Values that must match the backend \"s3\" block in ../main.tf"
  value       = <<-EOT
    bucket         = "${aws_s3_bucket.state.id}"
    key            = "infrastructure/terraform.tfstate"
    region         = "${var.aws_region}"
    encrypt        = true
    dynamodb_table = "${aws_dynamodb_table.locks.name}"
  EOT
}
