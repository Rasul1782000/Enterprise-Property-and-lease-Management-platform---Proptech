# ------------------------------------------------------------------------------
# Enterprise Property & Lease Management Portal
# AWS Infrastructure � Single-file Terraform Configuration
# ------------------------------------------------------------------------------


# -----------------------------------------------------------------------------
# SECTION: providers
# -----------------------------------------------------------------------------

terraform {
  required_version = ">= 1.6.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.80"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.6"
    }
  }

  backend "s3" {
    bucket         = "gaarbejana-terraform-state"
    key            = "infrastructure/terraform.tfstate"
    region         = "me-south-1"
    encrypt        = true
    dynamodb_table = "terraform-state-lock"
  }
}

provider "aws" {
  region = var.aws_region

  default_tags {
    tags = {
      Project     = "gaarbejana"
      Environment = var.environment
      ManagedBy   = "terraform"
    }
  }
}

provider "aws" {
  alias  = "us_east_1"
  region = "us-east-1"
}


# -----------------------------------------------------------------------------
# SECTION: variables
# -----------------------------------------------------------------------------

# ─── General ──────────────────────────────────────────────────────────────────

variable "aws_region" {
  description = "AWS region for all resources"
  type        = string
  default     = "me-south-1"
}

variable "environment" {
  description = "Deployment environment"
  type        = string
  default     = "production"

  validation {
    condition     = contains(["development", "staging", "production"], var.environment)
    error_message = "Environment must be development, staging, or production."
  }
}

variable "project_name" {
  description = "Project name used for resource naming"
  type        = string
  default     = "gaarbejana"
}

variable "app_domain" {
  description = "Primary application domain"
  type        = string
  default     = "rasul17.indevs.in"
}

variable "frontend_domain" {
  description = "Frontend domain (if not using Vercel)"
  type        = string
  default     = ""
}

# ─── Networking ───────────────────────────────────────────────────────────────

variable "vpc_cidr" {
  description = "CIDR block for the VPC"
  type        = string
  default     = "10.0.0.0/16"
}

variable "availability_zones" {
  description = "Availability zones to use"
  type        = list(string)
  default     = ["me-south-1a", "me-south-1b", "me-south-1c"]
}

variable "public_subnet_cidrs" {
  description = "CIDR blocks for public subnets"
  type        = list(string)
  default     = ["10.0.1.0/24", "10.0.2.0/24", "10.0.3.0/24"]
}

variable "private_subnet_cidrs" {
  description = "CIDR blocks for private subnets"
  type        = list(string)
  default     = ["10.0.10.0/24", "10.0.11.0/24", "10.0.12.0/24"]
}

variable "database_subnet_cidrs" {
  description = "CIDR blocks for database subnets"
  type        = list(string)
  default     = ["10.0.20.0/24", "10.0.21.0/24", "10.0.22.0/24"]
}

# ─── ECS / Backend ────────────────────────────────────────────────────────────

variable "backend_image" {
  description = "Docker image URI for the Laravel backend"
  type        = string
  default     = "gaarbejana-backend:latest"
}

variable "backend_cpu" {
  description = "CPU units for backend task (256 = 0.25 vCPU)"
  type        = number
  default     = 512
}

variable "backend_memory" {
  description = "Memory (MiB) for backend task"
  type        = number
  default     = 1024
}

variable "backend_desired_count" {
  description = "Desired number of backend tasks"
  type        = number
  default     = 2
}

variable "backend_min_count" {
  description = "Minimum number of backend tasks for autoscaling"
  type        = number
  default     = 2
}

variable "backend_max_count" {
  description = "Maximum number of backend tasks for autoscaling"
  type        = number
  default     = 6
}

# ─── RDS (MySQL) ──────────────────────────────────────────────────────────────

variable "db_instance_class" {
  description = "RDS instance class"
  type        = string
  default     = "db.t3.micro"
}

variable "db_engine_version" {
  description = "MySQL engine version"
  type        = string
  default     = "8.0.35"
}

variable "db_allocated_storage" {
  description = "Allocated storage in GB"
  type        = number
  default     = 20
}

variable "db_max_allocated_storage" {
  description = "Maximum storage for autoscaling in GB"
  type        = number
  default     = 100
}

variable "db_name" {
  description = "Database name"
  type        = string
  default     = "property_lease"
}

variable "db_username" {
  description = "Database master username"
  type        = string
  default     = "property_lease"
}

variable "db_multi_az" {
  description = "Enable Multi-AZ deployment"
  type        = bool
  default     = true
}

variable "db_backup_retention_days" {
  description = "Number of days to retain backups"
  type        = number
  default     = 7
}

# ─── ElastiCache (Redis) ─────────────────────────────────────────────────────

variable "redis_node_type" {
  description = "ElastiCache node type"
  type        = string
  default     = "cache.t3.micro"
}

variable "redis_num_cache_nodes" {
  description = "Number of cache nodes"
  type        = number
  default     = 1
}

variable "redis_engine_version" {
  description = "Redis engine version"
  type        = string
  default     = "7.1"
}

# ─── S3 ───────────────────────────────────────────────────────────────────────

variable "s3_document_bucket" {
  description = "S3 bucket name for documents"
  type        = string
  default     = "gaarbejana-documents"
}

variable "s3_media_bucket" {
  description = "S3 bucket name for media"
  type        = string
  default     = "gaarbejana-media"
}

variable "s3_state_bucket" {
  description = "S3 bucket name for Terraform state"
  type        = string
  default     = "gaarbejana-terraform-state"
}

variable "s3_versioning_enabled" {
  description = "Enable S3 versioning"
  type        = bool
  default     = true
}

# ─── CloudFront ───────────────────────────────────────────────────────────────

variable "enable_cloudfront" {
  description = "Enable CloudFront distribution for frontend"
  type        = bool
  default     = false
}

# ─── Monitoring ───────────────────────────────────────────────────────────────

variable "enable_monitoring" {
  description = "Enable CloudWatch monitoring and alarms"
  type        = bool
  default     = true
}

variable "alarm_email" {
  description = "Email for CloudWatch alarm notifications"
  type        = string
  default     = ""
}

# ─── Secrets ──────────────────────────────────────────────────────────────────

variable "app_key" {
  description = "Laravel APP_KEY"
  type        = string
  sensitive   = true
  default     = ""
}

variable "openwa_api_key" {
  description = "OpenWA API key"
  type        = string
  sensitive   = true
  default     = ""
}

variable "mail_password" {
  description = "SMTP password"
  type        = string
  sensitive   = true
  default     = ""
}

variable "aws_access_key_id" {
  description = "AWS access key ID used by the backend to reach the documents bucket"
  type        = string
  sensitive   = true
  default     = ""
}

variable "aws_secret_access_key" {
  description = "AWS secret access key used by the backend to reach the documents bucket"
  type        = string
  sensitive   = true
  default     = ""
}


# -----------------------------------------------------------------------------
# SECTION: vpc
# -----------------------------------------------------------------------------

# ─── VPC ──────────────────────────────────────────────────────────────────────

resource "aws_vpc" "main" {
  cidr_block           = var.vpc_cidr
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = {
    Name = "${var.project_name}-${var.environment}-vpc"
  }
}

# ─── Internet Gateway ─────────────────────────────────────────────────────────

resource "aws_internet_gateway" "main" {
  vpc_id = aws_vpc.main.id

  tags = {
    Name = "${var.project_name}-${var.environment}-igw"
  }
}

# ─── Public Subnets ───────────────────────────────────────────────────────────

resource "aws_subnet" "public" {
  count = length(var.public_subnet_cidrs)

  vpc_id                  = aws_vpc.main.id
  cidr_block              = var.public_subnet_cidrs[count.index]
  availability_zone       = var.availability_zones[count.index]
  map_public_ip_on_launch = true

  tags = {
    Name = "${var.project_name}-${var.environment}-public-${count.index + 1}"
    Type = "public"
  }
}

# ─── Private Subnets ─────────────────────────────────────────────────────────

resource "aws_subnet" "private" {
  count = length(var.private_subnet_cidrs)

  vpc_id            = aws_vpc.main.id
  cidr_block        = var.private_subnet_cidrs[count.index]
  availability_zone = var.availability_zones[count.index]

  tags = {
    Name = "${var.project_name}-${var.environment}-private-${count.index + 1}"
    Type = "private"
  }
}

# ─── Database Subnets ─────────────────────────────────────────────────────────

resource "aws_subnet" "database" {
  count = length(var.database_subnet_cidrs)

  vpc_id            = aws_vpc.main.id
  cidr_block        = var.database_subnet_cidrs[count.index]
  availability_zone = var.availability_zones[count.index]

  tags = {
    Name = "${var.project_name}-${var.environment}-database-${count.index + 1}"
    Type = "database"
  }
}

# ─── NAT Gateway (one per AZ for high availability) ───────────────────────────

resource "aws_eip" "nat" {
  count  = length(var.availability_zones)
  domain = "vpc"

  tags = {
    Name = "${var.project_name}-${var.environment}-nat-eip-${count.index + 1}"
  }

  depends_on = [aws_internet_gateway.main]
}

resource "aws_nat_gateway" "main" {
  count = length(var.availability_zones)

  allocation_id = aws_eip.nat[count.index].id
  subnet_id     = aws_subnet.public[count.index].id

  tags = {
    Name = "${var.project_name}-${var.environment}-nat-${count.index + 1}"
  }

  depends_on = [aws_internet_gateway.main]
}

# ─── Route Tables ─────────────────────────────────────────────────────────────

resource "aws_route_table" "public" {
  vpc_id = aws_vpc.main.id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.main.id
  }

  tags = {
    Name = "${var.project_name}-${var.environment}-public-rt"
  }
}

resource "aws_route_table_association" "public" {
  count = length(aws_subnet.public)

  subnet_id      = aws_subnet.public[count.index].id
  route_table_id = aws_route_table.public.id
}

resource "aws_route_table" "private" {
  count  = length(var.availability_zones)
  vpc_id = aws_vpc.main.id

  route {
    cidr_block     = "0.0.0.0/0"
    nat_gateway_id = aws_nat_gateway.main[count.index].id
  }

  tags = {
    Name = "${var.project_name}-${var.environment}-private-rt-${count.index + 1}"
  }
}

resource "aws_route_table_association" "private" {
  count = length(aws_subnet.private)

  subnet_id      = aws_subnet.private[count.index].id
  route_table_id = aws_route_table.private[count.index].id
}

# ─── DB Subnet Group ──────────────────────────────────────────────────────────

resource "aws_db_subnet_group" "main" {
  name       = "${var.project_name}-${var.environment}-db-subnet-group"
  subnet_ids = aws_subnet.database[*].id

  tags = {
    Name = "${var.project_name}-${var.environment}-db-subnet-group"
  }
}

# ─── ElastiCache Subnet Group ─────────────────────────────────────────────────

resource "aws_elasticache_subnet_group" "main" {
  name       = "${var.project_name}-${var.environment}-cache-subnet-group"
  subnet_ids = aws_subnet.private[*].id
}


# -----------------------------------------------------------------------------
# SECTION: security_groups
# -----------------------------------------------------------------------------

# ─── ALB Security Group ───────────────────────────────────────────────────────

resource "aws_security_group" "alb" {
  name_prefix = "${var.project_name}-${var.environment}-alb-"
  vpc_id      = aws_vpc.main.id
  description = "Security group for Application Load Balancer"

  ingress {
    description = "HTTPS from anywhere"
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    description = "HTTP from anywhere (redirects to HTTPS)"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    description = "Allow all outbound"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "${var.project_name}-${var.environment}-alb-sg"
  }

  lifecycle {
    create_before_destroy = true
  }
}

# ─── ECS / Backend Security Group ─────────────────────────────────────────────

resource "aws_security_group" "backend" {
  name_prefix = "${var.project_name}-${var.environment}-backend-"
  vpc_id      = aws_vpc.main.id
  description = "Security group for Laravel backend ECS tasks"

  ingress {
    description     = "HTTP from ALB"
    from_port       = 80
    to_port         = 80
    protocol        = "tcp"
    security_groups = [aws_security_group.alb.id]
  }

  egress {
    description = "Allow all outbound"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "${var.project_name}-${var.environment}-backend-sg"
  }

  lifecycle {
    create_before_destroy = true
  }
}

# ─── RDS Security Group ───────────────────────────────────────────────────────

resource "aws_security_group" "database" {
  name_prefix = "${var.project_name}-${var.environment}-db-"
  vpc_id      = aws_vpc.main.id
  description = "Security group for RDS MySQL"

  ingress {
    description     = "MySQL from backend"
    from_port       = 3306
    to_port         = 3306
    protocol        = "tcp"
    security_groups = [aws_security_group.backend.id]
  }

  ingress {
    description = "MySQL from bastion (if needed)"
    from_port   = 3306
    to_port     = 3306
    protocol    = "tcp"
    cidr_blocks = [var.vpc_cidr]
  }

  egress {
    description = "Allow all outbound"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "${var.project_name}-${var.environment}-db-sg"
  }

  lifecycle {
    create_before_destroy = true
  }
}

# ─── ElastiCache Security Group ───────────────────────────────────────────────

resource "aws_security_group" "redis" {
  name_prefix = "${var.project_name}-${var.environment}-redis-"
  vpc_id      = aws_vpc.main.id
  description = "Security group for ElastiCache Redis"

  ingress {
    description     = "Redis from backend"
    from_port       = 6379
    to_port         = 6379
    protocol        = "tcp"
    security_groups = [aws_security_group.backend.id]
  }

  egress {
    description = "Allow all outbound"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "${var.project_name}-${var.environment}-redis-sg"
  }

  lifecycle {
    create_before_destroy = true
  }
}


# -----------------------------------------------------------------------------
# SECTION: rds
# -----------------------------------------------------------------------------

# ─── RDS Parameter Group ──────────────────────────────────────────────────────

resource "aws_db_parameter_group" "main" {
  name   = "${var.project_name}-${var.environment}-mysql8"
  family = "mysql8.0"

  parameter {
    name  = "character_set_server"
    value = "utf8mb4"
  }

  parameter {
    name  = "character_set_client"
    value = "utf8mb4"
  }

  parameter {
    name  = "collation_server"
    value = "utf8mb4_unicode_ci"
  }

  parameter {
    name  = "max_connections"
    value = "500"
  }

  parameter {
    name         = "innodb_buffer_pool_size"
    value        = "{DBInstanceClassMemory*3/4}"
    apply_method = "pending-reboot"
  }

  tags = {
    Name = "${var.project_name}-${var.environment}-mysql8-params"
  }

  lifecycle {
    create_before_destroy = true
  }
}

# ─── RDS Instance ─────────────────────────────────────────────────────────────

resource "random_password" "db_password" {
  length           = 32
  special          = true
  override_special = "!#$%&*()-_=+[]{}<>:?"
}

resource "aws_db_instance" "main" {
  identifier = "${var.project_name}-${var.environment}-db"

  engine         = "mysql"
  engine_version = var.db_engine_version
  instance_class = var.db_instance_class

  allocated_storage     = var.db_allocated_storage
  max_allocated_storage = var.db_max_allocated_storage
  storage_type          = "gp3"
  storage_encrypted     = true

  db_name  = var.db_name
  username = var.db_username
  password = random_password.db_password.result
  port     = 3306

  multi_az               = var.db_multi_az
  db_subnet_group_name   = aws_db_subnet_group.main.name
  vpc_security_group_ids = [aws_security_group.database.id]
  parameter_group_name   = aws_db_parameter_group.main.name

  backup_retention_period         = var.db_backup_retention_days
  backup_window                   = "03:00-04:00"
  maintenance_window              = "sun:04:00-sun:05:00"
  enabled_cloudwatch_logs_exports = ["error", "general", "slowquery"]

  deletion_protection       = var.environment == "production" ? true : false
  skip_final_snapshot       = var.environment == "production" ? false : true
  final_snapshot_identifier = var.environment == "production" ? "${var.project_name}-${var.environment}-final-snapshot" : null

  performance_insights_enabled          = true
  performance_insights_retention_period = 7

  auto_minor_version_upgrade = true

  tags = {
    Name = "${var.project_name}-${var.environment}-mysql"
  }
}

# ─── Store DB password in Secrets Manager ─────────────────────────────────────

resource "aws_secretsmanager_secret" "db_password" {
  name                    = "${var.project_name}/${var.environment}/db-password"
  description             = "RDS MySQL password for ${var.project_name}"
  recovery_window_in_days = var.environment == "production" ? 30 : 7

  tags = {
    Name = "${var.project_name}-${var.environment}-db-password"
  }
}

resource "aws_secretsmanager_secret_version" "db_password" {
  secret_id     = aws_secretsmanager_secret.db_password.id
  secret_string = random_password.db_password.result
}


# -----------------------------------------------------------------------------
# SECTION: elasticache
# -----------------------------------------------------------------------------

# ─── ElastiCache Parameter Group ─────────────────────────────────────────────

resource "aws_elasticache_parameter_group" "main" {
  name   = "${var.project_name}-${var.environment}-redis7"
  family = "redis7"

  parameter {
    name  = "maxmemory-policy"
    value = "allkeys-lru"
  }
}

# ─── ElastiCache Replication Group ────────────────────────────────────────────

resource "aws_elasticache_replication_group" "main" {
  replication_group_id = "${var.project_name}-${var.environment}-redis"
  description          = "Redis cluster for ${var.project_name} ${var.environment}"

  engine         = "redis"
  engine_version = var.redis_engine_version
  node_type      = var.redis_node_type

  num_cache_clusters         = var.redis_num_cache_nodes
  automatic_failover_enabled = var.redis_num_cache_nodes > 1 ? true : false
  multi_az_enabled           = var.redis_num_cache_nodes > 1 ? true : false

  at_rest_encryption_enabled = true
  transit_encryption_enabled = true

  subnet_group_name  = aws_elasticache_subnet_group.main.name
  security_group_ids = [aws_security_group.redis.id]

  parameter_group_name = aws_elasticache_parameter_group.main.name

  snapshot_retention_limit = 7
  snapshot_window          = "05:00-06:00"
  maintenance_window       = "sun:06:00-sun:07:00"

  auto_minor_version_upgrade = true

  tags = {
    Name = "${var.project_name}-${var.environment}-redis"
  }
}


# -----------------------------------------------------------------------------
# SECTION: s3
# -----------------------------------------------------------------------------

# ─── S3 Bucket: Documents ─────────────────────────────────────────────────────

resource "aws_s3_bucket" "documents" {
  bucket = "${var.s3_document_bucket}-${var.environment}"

  tags = {
    Name = "${var.project_name}-${var.environment}-documents"
  }
}

resource "aws_s3_bucket_versioning" "documents" {
  bucket = aws_s3_bucket.documents.id

  versioning_configuration {
    status = var.s3_versioning_enabled ? "Enabled" : "Disabled"
  }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "documents" {
  bucket = aws_s3_bucket.documents.id

  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
    bucket_key_enabled = true
  }
}

resource "aws_s3_bucket_public_access_block" "documents" {
  bucket = aws_s3_bucket.documents.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

resource "aws_s3_bucket_cors_configuration" "documents" {
  bucket = aws_s3_bucket.documents.id

  cors_rule {
    allowed_headers = ["*"]
    allowed_methods = ["GET", "PUT", "POST", "DELETE"]
    allowed_origins = var.frontend_domain != "" ? ["https://${var.frontend_domain}", "https://${var.app_domain}"] : ["https://${var.app_domain}"]
    expose_headers  = ["ETag"]
    max_age_seconds = 3000
  }
}

resource "aws_s3_bucket_lifecycle_configuration" "documents" {
  bucket = aws_s3_bucket.documents.id

  rule {
    id     = "transition-to-ia"
    status = "Enabled"

    filter {}

    transition {
      days          = 90
      storage_class = "STANDARD_IA"
    }

    transition {
      days          = 365
      storage_class = "GLACIER"
    }
  }
}

# ─── S3 Bucket: Media ──────────────────────────────────────────────────────────

resource "aws_s3_bucket" "media" {
  bucket = "${var.s3_media_bucket}-${var.environment}"

  tags = {
    Name = "${var.project_name}-${var.environment}-media"
  }
}

resource "aws_s3_bucket_versioning" "media" {
  bucket = aws_s3_bucket.media.id

  versioning_configuration {
    status = var.s3_versioning_enabled ? "Enabled" : "Disabled"
  }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "media" {
  bucket = aws_s3_bucket.media.id

  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
    bucket_key_enabled = true
  }
}

resource "aws_s3_bucket_public_access_block" "media" {
  bucket = aws_s3_bucket.media.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

# ─── S3 Bucket: Terraform State ───────────────────────────────────────────────

resource "aws_s3_bucket" "terraform_state" {
  bucket = "${var.s3_state_bucket}-${var.environment}"

  tags = {
    Name = "${var.project_name}-${var.environment}-tfstate"
  }
}

resource "aws_s3_bucket_versioning" "terraform_state" {
  bucket = aws_s3_bucket.terraform_state.id

  versioning_configuration {
    status = "Enabled"
  }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "terraform_state" {
  bucket = aws_s3_bucket.terraform_state.id

  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
    bucket_key_enabled = true
  }
}

resource "aws_s3_bucket_public_access_block" "terraform_state" {
  bucket = aws_s3_bucket.terraform_state.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

# ─── DynamoDB Table for Terraform State Locking ───────────────────────────────

resource "aws_dynamodb_table" "terraform_locks" {
  name         = "terraform-state-lock-${var.environment}"
  billing_mode = "PAY_PER_REQUEST"
  hash_key     = "LockID"

  attribute {
    name = "LockID"
    type = "S"
  }

  point_in_time_recovery {
    enabled = true
  }

  tags = {
    Name = "${var.project_name}-${var.environment}-tf-locks"
  }
}


# -----------------------------------------------------------------------------
# SECTION: ecs
# -----------------------------------------------------------------------------

# ─── ECS Cluster ──────────────────────────────────────────────────────────────

resource "aws_ecs_cluster" "main" {
  name = "${var.project_name}-${var.environment}-cluster"

  setting {
    name  = "containerInsights"
    value = "enabled"
  }

  configuration {
    execute_command_configuration {
      logging = "OVERRIDE"

      log_configuration {
        cloud_watch_encryption_enabled = true
        cloud_watch_log_group_name     = aws_cloudwatch_log_group.ecs_exec.name
      }
    }
  }

  tags = {
    Name = "${var.project_name}-${var.environment}-cluster"
  }
}

resource "aws_cloudwatch_log_group" "ecs_exec" {
  name              = "/ecs/${var.project_name}-${var.environment}/exec"
  retention_in_days = 7
}

# ─── ECS Cluster Capacity Providers ───────────────────────────────────────────

resource "aws_ecs_cluster_capacity_providers" "main" {
  cluster_name = aws_ecs_cluster.main.name

  capacity_providers = ["FARGATE", "FARGATE_SPOT"]

  default_capacity_provider_strategy {
    base              = 1
    weight            = 1
    capacity_provider = "FARGATE"
  }

  default_capacity_provider_strategy {
    weight            = 3
    capacity_provider = "FARGATE_SPOT"
  }
}

# ─── CloudWatch Log Group for Backend ─────────────────────────────────────────


# ─── IAM Role for ECS Task Execution ──────────────────────────────────────────

resource "aws_iam_role" "ecs_task_execution" {
  name = "${var.project_name}-${var.environment}-ecs-task-execution"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "ecs-tasks.amazonaws.com"
        }
      }
    ]
  })

  tags = {
    Name = "${var.project_name}-${var.environment}-ecs-task-execution"
  }
}

resource "aws_iam_role_policy_attachment" "ecs_task_execution" {
  role       = aws_iam_role.ecs_task_execution.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

resource "aws_iam_role_policy" "ecs_task_execution_secrets" {
  name = "${var.project_name}-${var.environment}-ecs-secrets"
  role = aws_iam_role.ecs_task_execution.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "secretsmanager:GetSecretValue",
          "kms:Decrypt"
        ]
        Resource = [
          aws_secretsmanager_secret.db_password.arn,
          aws_secretsmanager_secret.app_key.arn,
          aws_secretsmanager_secret.openwa.arn,
          aws_secretsmanager_secret.mail.arn,
          aws_secretsmanager_secret.aws_access_key_id.arn,
          aws_secretsmanager_secret.aws_secret_access_key.arn
        ]
      },
      {
        Effect = "Allow"
        Action = [
          "s3:GetObject",
          "s3:PutObject",
          "s3:DeleteObject",
          "s3:ListBucket"
        ]
        Resource = [
          aws_s3_bucket.documents.arn,
          "${aws_s3_bucket.documents.arn}/*",
          aws_s3_bucket.media.arn,
          "${aws_s3_bucket.media.arn}/*"
        ]
      },
      {
        Effect = "Allow"
        Action = [
          "logs:CreateLogStream",
          "logs:PutLogEvents"
        ]
        Resource = "${aws_cloudwatch_log_group.backend.arn}:*"
      }
    ]
  })
}

# ─── IAM Role for ECS Tasks (application-level permissions) ───────────────────

resource "aws_iam_role" "ecs_task" {
  name = "${var.project_name}-${var.environment}-ecs-task"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "ecs-tasks.amazonaws.com"
        }
      }
    ]
  })

  tags = {
    Name = "${var.project_name}-${var.environment}-ecs-task"
  }
}

resource "aws_iam_role_policy" "ecs_task_s3" {
  name = "${var.project_name}-${var.environment}-task-s3"
  role = aws_iam_role.ecs_task.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "s3:GetObject",
          "s3:PutObject",
          "s3:DeleteObject",
          "s3:ListBucket"
        ]
        Resource = [
          aws_s3_bucket.documents.arn,
          "${aws_s3_bucket.documents.arn}/*",
          aws_s3_bucket.media.arn,
          "${aws_s3_bucket.media.arn}/*"
        ]
      },
      {
        Effect = "Allow"
        Action = [
          "logs:CreateLogStream",
          "logs:PutLogEvents"
        ]
        Resource = "${aws_cloudwatch_log_group.backend.arn}:*"
      }
    ]
  })
}

# ─── ECS Task Definition ──────────────────────────────────────────────────────

resource "aws_ecs_task_definition" "backend" {
  family                   = "${var.project_name}-${var.environment}-backend"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = var.backend_cpu
  memory                   = var.backend_memory
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn
  task_role_arn            = aws_iam_role.ecs_task.arn

  container_definitions = jsonencode([
    {
      name      = "backend"
      image     = var.backend_image
      essential = true

      portMappings = [
        {
          containerPort = 80
          protocol      = "tcp"
        }
      ]

      environment = [
        { name = "APP_ENV", value = var.environment },
        { name = "APP_URL", value = "https://${var.app_domain}" },
        { name = "DB_CONNECTION", value = "mysql" },
        { name = "DB_HOST", value = aws_db_instance.main.address },
        { name = "DB_PORT", value = "3306" },
        { name = "DB_DATABASE", value = var.db_name },
        { name = "DB_USERNAME", value = var.db_username },
        { name = "REDIS_HOST", value = aws_elasticache_replication_group.main.primary_endpoint_address },
        { name = "REDIS_PORT", value = "6379" },
        { name = "REDIS_CLIENT", value = "phpredis" },
        { name = "CACHE_DRIVER", value = "redis" },
        { name = "SESSION_DRIVER", value = "redis" },
        { name = "QUEUE_CONNECTION", value = "redis" },
        { name = "FILESYSTEM_DISK", value = "documents" },
        { name = "AWS_STORAGE_BUCKET", value = aws_s3_bucket.documents.id },
        { name = "AWS_STORAGE_PREFIX", value = "portal" },
        { name = "AWS_DEFAULT_REGION", value = var.aws_region },
        { name = "AWS_USE_PATH_STYLE_ENDPOINT", value = "false" },
        { name = "PROMETHEUS_ENABLED", value = "true" },
        { name = "PROMETHEUS_NAMESPACE", value = "propertylease_api" }
      ]

      secrets = [
        {
          name      = "APP_KEY"
          valueFrom = aws_secretsmanager_secret.app_key.arn
        },
        {
          name      = "DB_PASSWORD"
          valueFrom = aws_secretsmanager_secret.db_password.arn
        },
        {
          name      = "OPENWA_API_KEY"
          valueFrom = aws_secretsmanager_secret.openwa.arn
        },
        {
          name      = "MAIL_PASSWORD"
          valueFrom = aws_secretsmanager_secret.mail.arn
        },
        {
          name      = "AWS_ACCESS_KEY_ID"
          valueFrom = aws_secretsmanager_secret.aws_access_key_id.arn
        },
        {
          name      = "AWS_SECRET_ACCESS_KEY"
          valueFrom = aws_secretsmanager_secret.aws_secret_access_key.arn
        }
      ]

      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = aws_cloudwatch_log_group.backend.name
          "awslogs-region"        = var.aws_region
          "awslogs-stream-prefix" = "backend"
        }
      }

      healthCheck = {
        command     = ["CMD-SHELL", "curl -f http://localhost/up || exit 1"]
        interval    = 30
        timeout     = 5
        retries     = 3
        startPeriod = 60
      }

      ulimits = [
        {
          name      = "nofile"
          softLimit = 65536
          hardLimit = 65536
        }
      ]
    }
  ])

  tags = {
    Name = "${var.project_name}-${var.environment}-backend-task"
  }
}

# ─── ECS Service ──────────────────────────────────────────────────────────────

resource "aws_ecs_service" "backend" {
  name            = "${var.project_name}-${var.environment}-backend"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.backend.arn
  desired_count   = var.backend_desired_count
  launch_type     = "FARGATE"

  network_configuration {
    subnets          = aws_subnet.private[*].id
    security_groups  = [aws_security_group.backend.id]
    assign_public_ip = false
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.backend.arn
    container_name   = "backend"
    container_port   = 80
  }

  deployment_circuit_breaker {
    enable   = true
    rollback = true
  }

  deployment_maximum_percent         = 200
  deployment_minimum_healthy_percent = 100

  propagate_tags = "SERVICE"

  tags = {
    Name = "${var.project_name}-${var.environment}-backend-service"
  }

  depends_on = [aws_lb_listener.https, aws_iam_role_policy_attachment.ecs_task_execution]
}

# ─── ECS Auto Scaling ─────────────────────────────────────────────────────────

resource "aws_appautoscaling_target" "backend" {
  max_capacity       = var.backend_max_count
  min_capacity       = var.backend_min_count
  resource_id        = "service/${aws_ecs_cluster.main.name}/${aws_ecs_service.backend.name}"
  scalable_dimension = "ecs:service:DesiredCount"
  service_namespace  = "ecs"
}

resource "aws_appautoscaling_policy" "backend_cpu" {
  name               = "${var.project_name}-${var.environment}-backend-cpu"
  policy_type        = "TargetTrackingScaling"
  resource_id        = aws_appautoscaling_target.backend.resource_id
  scalable_dimension = aws_appautoscaling_target.backend.scalable_dimension
  service_namespace  = aws_appautoscaling_target.backend.service_namespace

  target_tracking_scaling_policy_configuration {
    predefined_metric_specification {
      predefined_metric_type = "ECSServiceAverageCPUUtilization"
    }
    target_value       = 70.0
    scale_in_cooldown  = 300
    scale_out_cooldown = 60
  }
}

resource "aws_appautoscaling_policy" "backend_memory" {
  name               = "${var.project_name}-${var.environment}-backend-memory"
  policy_type        = "TargetTrackingScaling"
  resource_id        = aws_appautoscaling_target.backend.resource_id
  scalable_dimension = aws_appautoscaling_target.backend.scalable_dimension
  service_namespace  = aws_appautoscaling_target.backend.service_namespace

  target_tracking_scaling_policy_configuration {
    predefined_metric_specification {
      predefined_metric_type = "ECSServiceAverageMemoryUtilization"
    }
    target_value       = 75.0
    scale_in_cooldown  = 300
    scale_out_cooldown = 60
  }
}


# -----------------------------------------------------------------------------
# SECTION: alb
# -----------------------------------------------------------------------------

# ─── Application Load Balancer ────────────────────────────────────────────────

resource "aws_lb" "main" {
  name               = "${var.project_name}-${var.environment}-alb"
  internal           = false
  load_balancer_type = "application"
  security_groups    = [aws_security_group.alb.id]
  subnets            = aws_subnet.public[*].id

  enable_deletion_protection = var.environment == "production" ? true : false
  enable_http2               = true
  idle_timeout               = 60

  access_logs {
    bucket  = aws_s3_bucket.logs.id
    prefix  = "alb-logs"
    enabled = true
  }

  tags = {
    Name = "${var.project_name}-${var.environment}-alb"
  }
}

# ─── S3 Bucket for ALB Logs ──────────────────────────────────────────────────

resource "aws_s3_bucket" "logs" {
  bucket = "${var.project_name}-${var.environment}-alb-logs"

  tags = {
    Name = "${var.project_name}-${var.environment}-alb-logs"
  }
}

resource "aws_s3_bucket_lifecycle_configuration" "logs" {
  bucket = aws_s3_bucket.logs.id

  rule {
    id     = "archive-old-logs"
    status = "Enabled"

    filter {
      prefix = "alb-logs/"
    }

    transition {
      days          = 30
      storage_class = "STANDARD_IA"
    }

    transition {
      days          = 90
      storage_class = "GLACIER"
    }

    expiration {
      days = 365
    }
  }
}

# ─── Target Group ─────────────────────────────────────────────────────────────

resource "aws_lb_target_group" "backend" {
  name        = "${var.project_name}-${var.environment}-backend-tg"
  port        = 80
  protocol    = "HTTP"
  vpc_id      = aws_vpc.main.id
  target_type = "ip"

  health_check {
    enabled             = true
    healthy_threshold   = 2
    interval            = 30
    matcher             = "200"
    path                = "/up"
    port                = "traffic-port"
    protocol            = "HTTP"
    timeout             = 5
    unhealthy_threshold = 3
  }

  deregistration_delay = 30

  tags = {
    Name = "${var.project_name}-${var.environment}-backend-tg"
  }

  lifecycle {
    create_before_destroy = true
  }
}

# ─── HTTPS Listener ───────────────────────────────────────────────────────────

resource "aws_lb_listener" "https" {
  load_balancer_arn = aws_lb.main.arn
  port              = 443
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS13-1-2-2021-06"
  certificate_arn   = aws_acm_certificate.main.arn

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.backend.arn
  }

  tags = {
    Name = "${var.project_name}-${var.environment}-https-listener"
  }
}

# ─── HTTP Listener (redirects to HTTPS) ───────────────────────────────────────

resource "aws_lb_listener" "http" {
  load_balancer_arn = aws_lb.main.arn
  port              = 80
  protocol          = "HTTP"

  default_action {
    type = "redirect"

    redirect {
      port        = "443"
      protocol    = "HTTPS"
      status_code = "HTTP_301"
    }
  }

  tags = {
    Name = "${var.project_name}-${var.environment}-http-listener"
  }
}


# -----------------------------------------------------------------------------
# SECTION: acm
# -----------------------------------------------------------------------------

# ─── ACM Certificate ──────────────────────────────────────────────────────────

resource "aws_acm_certificate" "main" {
  provider = aws.us_east_1

  domain_name               = var.app_domain
  subject_alternative_names = ["*.${var.app_domain}"]
  validation_method         = "DNS"

  tags = {
    Name = "${var.project_name}-${var.environment}-cert"
  }

  lifecycle {
    create_before_destroy = true
  }
}

# ─── Route 53 Zone ────────────────────────────────────────────────────────────

data "aws_route53_zone" "main" {
  name         = var.app_domain
  private_zone = false
}

# ─── Route 53 Records for ACM Validation ──────────────────────────────────────

resource "aws_route53_record" "cert_validation" {
  for_each = {
    for dvo in aws_acm_certificate.main.domain_validation_options : dvo.domain_name => {
      name   = dvo.resource_record_name
      record = dvo.resource_record_value
      type   = dvo.resource_record_type
    }
  }

  allow_overwrite = true
  name            = each.value.name
  records         = [each.value.record]
  ttl             = 60
  type            = each.value.type
  zone_id         = data.aws_route53_zone.main.zone_id
}

resource "aws_acm_certificate_validation" "main" {
  provider = aws.us_east_1

  certificate_arn         = aws_acm_certificate.main.arn
  validation_record_fqdns = [for record in aws_route53_record.cert_validation : record.fqdn]
}

# ─── Route 53 Record for API ──────────────────────────────────────────────────

resource "aws_route53_record" "api" {
  zone_id = data.aws_route53_zone.main.zone_id
  name    = "api.${var.app_domain}"
  type    = "A"

  alias {
    name                   = aws_lb.main.dns_name
    zone_id                = aws_lb.main.zone_id
    evaluate_target_health = true
  }
}

# ─── Route 53 Record for Root Domain ──────────────────────────────────────────

resource "aws_route53_record" "root" {
  zone_id = data.aws_route53_zone.main.zone_id
  name    = var.app_domain
  type    = "A"

  alias {
    name                   = aws_lb.main.dns_name
    zone_id                = aws_lb.main.zone_id
    evaluate_target_health = true
  }
}


# -----------------------------------------------------------------------------
# SECTION: secrets
# -----------------------------------------------------------------------------

# ─── Secrets Manager: APP_KEY ─────────────────────────────────────────────────

resource "aws_secretsmanager_secret" "app_key" {
  name                    = "${var.project_name}/${var.environment}/app-key"
  description             = "Laravel APP_KEY for ${var.project_name}"
  recovery_window_in_days = var.environment == "production" ? 30 : 7

  tags = {
    Name = "${var.project_name}-${var.environment}-app-key"
  }
}

resource "aws_secretsmanager_secret_version" "app_key" {
  secret_id     = aws_secretsmanager_secret.app_key.id
  secret_string = var.app_key
}

# ─── Secrets Manager: OpenWA API Key ──────────────────────────────────────────

resource "aws_secretsmanager_secret" "openwa" {
  name                    = "${var.project_name}/${var.environment}/openwa-api-key"
  description             = "OpenWA API key for ${var.project_name}"
  recovery_window_in_days = var.environment == "production" ? 30 : 7

  tags = {
    Name = "${var.project_name}-${var.environment}-openwa-key"
  }
}

resource "aws_secretsmanager_secret_version" "openwa" {
  secret_id     = aws_secretsmanager_secret.openwa.id
  secret_string = var.openwa_api_key
}

# ─── Secrets Manager: Mail Password ───────────────────────────────────────────

resource "aws_secretsmanager_secret" "mail" {
  name                    = "${var.project_name}/${var.environment}/mail-password"
  description             = "SMTP password for ${var.project_name}"
  recovery_window_in_days = var.environment == "production" ? 30 : 7

  tags = {
    Name = "${var.project_name}-${var.environment}-mail-password"
  }
}

resource "aws_secretsmanager_secret_version" "mail" {
  secret_id     = aws_secretsmanager_secret.mail.id
  secret_string = var.mail_password
}

# ─── Secrets Manager: AWS Access Key ID ────────────────────────────────────────

resource "aws_secretsmanager_secret" "aws_access_key_id" {
  name                    = "${var.project_name}/${var.environment}/aws-access-key-id"
  description             = "AWS access key ID for the documents bucket"
  recovery_window_in_days = var.environment == "production" ? 30 : 7

  tags = {
    Name = "${var.project_name}-${var.environment}-aws-access-key-id"
  }
}

resource "aws_secretsmanager_secret_version" "aws_access_key_id" {
  secret_id     = aws_secretsmanager_secret.aws_access_key_id.id
  secret_string = var.aws_access_key_id
}

# ─── Secrets Manager: AWS Secret Access Key ────────────────────────────────────

resource "aws_secretsmanager_secret" "aws_secret_access_key" {
  name                    = "${var.project_name}/${var.environment}/aws-secret-access-key"
  description             = "AWS secret access key for the documents bucket"
  recovery_window_in_days = var.environment == "production" ? 30 : 7

  tags = {
    Name = "${var.project_name}-${var.environment}-aws-secret-access-key"
  }
}

resource "aws_secretsmanager_secret_version" "aws_secret_access_key" {
  secret_id     = aws_secretsmanager_secret.aws_secret_access_key.id
  secret_string = var.aws_secret_access_key
}


# -----------------------------------------------------------------------------
# SECTION: cloudwatch
# -----------------------------------------------------------------------------

# ─── CloudWatch Log Group for Backend ─────────────────────────────────────────

resource "aws_cloudwatch_log_group" "backend" {
  name              = "/ecs/${var.project_name}-${var.environment}/backend"
  retention_in_days = 30

  tags = {
    Name = "${var.project_name}-${var.environment}-backend-logs"
  }
}

# ─── CloudWatch Log Metric Filter: 5xx Errors ─────────────────────────────────

resource "aws_cloudwatch_log_metric_filter" "http_5xx" {
  name           = "${var.project_name}-${var.environment}-http-5xx"
  pattern        = "{ $.status = 5* }"
  log_group_name = aws_cloudwatch_log_group.backend.name

  metric_transformation {
    name          = "HTTP5xxCount"
    namespace     = "${var.project_name}/${var.environment}"
    value         = "1"
    default_value = "0"
  }
}

# ─── CloudWatch Log Metric Filter: 4xx Errors ─────────────────────────────────

resource "aws_cloudwatch_log_metric_filter" "http_4xx" {
  name           = "${var.project_name}-${var.environment}-http-4xx"
  pattern        = "{ $.status = 4* }"
  log_group_name = aws_cloudwatch_log_group.backend.name

  metric_transformation {
    name          = "HTTP4xxCount"
    namespace     = "${var.project_name}/${var.environment}"
    value         = "1"
    default_value = "0"
  }
}

# ─── CloudWatch Alarm: High 5xx Error Rate ────────────────────────────────────

resource "aws_cloudwatch_metric_alarm" "high_5xx" {
  count = var.enable_monitoring ? 1 : 0

  alarm_name          = "${var.project_name}-${var.environment}-high-5xx"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 2
  metric_name         = "HTTP5xxCount"
  namespace           = "${var.project_name}/${var.environment}"
  period              = 300
  statistic           = "Sum"
  threshold           = 50
  alarm_description   = "This metric monitors HTTP 5xx errors from the backend"
  alarm_actions       = var.alarm_email != "" ? [aws_sns_topic.alarms[0].arn] : []
  ok_actions          = var.alarm_email != "" ? [aws_sns_topic.alarms[0].arn] : []

  tags = {
    Name = "${var.project_name}-${var.environment}-high-5xx-alarm"
  }
}

# ─── CloudWatch Alarm: High CPU Utilization ───────────────────────────────────

resource "aws_cloudwatch_metric_alarm" "high_cpu" {
  count = var.enable_monitoring ? 1 : 0

  alarm_name          = "${var.project_name}-${var.environment}-high-cpu"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 3
  metric_name         = "CPUUtilization"
  namespace           = "AWS/ECS"
  period              = 300
  statistic           = "Average"
  threshold           = 80
  alarm_description   = "This metric monitors ECS cluster CPU utilization"
  alarm_actions       = var.alarm_email != "" ? [aws_sns_topic.alarms[0].arn] : []
  ok_actions          = var.alarm_email != "" ? [aws_sns_topic.alarms[0].arn] : []

  dimensions = {
    ClusterName = aws_ecs_cluster.main.name
    ServiceName = aws_ecs_service.backend.name
  }

  tags = {
    Name = "${var.project_name}-${var.environment}-high-cpu-alarm"
  }
}

# ─── CloudWatch Alarm: High Memory Utilization ────────────────────────────────

resource "aws_cloudwatch_metric_alarm" "high_memory" {
  count = var.enable_monitoring ? 1 : 0

  alarm_name          = "${var.project_name}-${var.environment}-high-memory"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 3
  metric_name         = "MemoryUtilization"
  namespace           = "AWS/ECS"
  period              = 300
  statistic           = "Average"
  threshold           = 85
  alarm_description   = "This metric monitors ECS cluster memory utilization"
  alarm_actions       = var.alarm_email != "" ? [aws_sns_topic.alarms[0].arn] : []
  ok_actions          = var.alarm_email != "" ? [aws_sns_topic.alarms[0].arn] : []

  dimensions = {
    ClusterName = aws_ecs_cluster.main.name
    ServiceName = aws_ecs_service.backend.name
  }

  tags = {
    Name = "${var.project_name}-${var.environment}-high-memory-alarm"
  }
}

# ─── CloudWatch Alarm: RDS High CPU ───────────────────────────────────────────

resource "aws_cloudwatch_metric_alarm" "rds_high_cpu" {
  count = var.enable_monitoring ? 1 : 0

  alarm_name          = "${var.project_name}-${var.environment}-rds-high-cpu"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 3
  metric_name         = "CPUUtilization"
  namespace           = "AWS/RDS"
  period              = 300
  statistic           = "Average"
  threshold           = 80
  alarm_description   = "This metric monitors RDS CPU utilization"
  alarm_actions       = var.alarm_email != "" ? [aws_sns_topic.alarms[0].arn] : []
  ok_actions          = var.alarm_email != "" ? [aws_sns_topic.alarms[0].arn] : []

  dimensions = {
    DBInstanceIdentifier = aws_db_instance.main.id
  }

  tags = {
    Name = "${var.project_name}-${var.environment}-rds-cpu-alarm"
  }
}

# ─── CloudWatch Alarm: RDS Low Storage ────────────────────────────────────────

resource "aws_cloudwatch_metric_alarm" "rds_low_storage" {
  count = var.enable_monitoring ? 1 : 0

  alarm_name          = "${var.project_name}-${var.environment}-rds-low-storage"
  comparison_operator = "LessThanThreshold"
  evaluation_periods  = 1
  metric_name         = "FreeStorageSpace"
  namespace           = "AWS/RDS"
  period              = 300
  statistic           = "Average"
  threshold           = 5368709120 # 5 GB in bytes
  alarm_description   = "This metric monitors RDS free storage space"
  alarm_actions       = var.alarm_email != "" ? [aws_sns_topic.alarms[0].arn] : []
  ok_actions          = var.alarm_email != "" ? [aws_sns_topic.alarms[0].arn] : []

  dimensions = {
    DBInstanceIdentifier = aws_db_instance.main.id
  }

  tags = {
    Name = "${var.project_name}-${var.environment}-rds-storage-alarm"
  }
}

# ─── SNS Topic for Alarms ─────────────────────────────────────────────────────

resource "aws_sns_topic" "alarms" {
  count = var.enable_monitoring && var.alarm_email != "" ? 1 : 0

  name = "${var.project_name}-${var.environment}-alarms"

  tags = {
    Name = "${var.project_name}-${var.environment}-alarms"
  }
}

resource "aws_sns_topic_subscription" "email" {
  count = var.enable_monitoring && var.alarm_email != "" ? 1 : 0

  topic_arn = aws_sns_topic.alarms[0].arn
  protocol  = "email"
  endpoint  = var.alarm_email
}

# ─── CloudWatch Dashboard ─────────────────────────────────────────────────────

resource "aws_cloudwatch_dashboard" "main" {
  count = var.enable_monitoring ? 1 : 0

  dashboard_name = "${var.project_name}-${var.environment}-dashboard"

  dashboard_body = jsonencode({
    widgets = [
      {
        type   = "metric"
        x      = 0
        y      = 0
        width  = 12
        height = 6
        properties = {
          title  = "ECS CPU Utilization"
          region = var.aws_region
          metrics = [
            ["AWS/ECS", "CPUUtilization", "ClusterName", aws_ecs_cluster.main.name, "ServiceName", aws_ecs_service.backend.name]
          ]
          period = 300
          stat   = "Average"
        }
      },
      {
        type   = "metric"
        x      = 12
        y      = 0
        width  = 12
        height = 6
        properties = {
          title  = "ECS Memory Utilization"
          region = var.aws_region
          metrics = [
            ["AWS/ECS", "MemoryUtilization", "ClusterName", aws_ecs_cluster.main.name, "ServiceName", aws_ecs_service.backend.name]
          ]
          period = 300
          stat   = "Average"
        }
      },
      {
        type   = "metric"
        x      = 0
        y      = 6
        width  = 12
        height = 6
        properties = {
          title  = "ALB Request Count"
          region = var.aws_region
          metrics = [
            ["AWS/ApplicationELB", "RequestCount", "LoadBalancer", aws_lb.main.arn_suffix]
          ]
          period = 300
          stat   = "Sum"
        }
      },
      {
        type   = "metric"
        x      = 12
        y      = 6
        width  = 12
        height = 6
        properties = {
          title  = "ALB Target Response Time"
          region = var.aws_region
          metrics = [
            ["AWS/ApplicationELB", "TargetResponseTime", "LoadBalancer", aws_lb.main.arn_suffix]
          ]
          period = 300
          stat   = "Average"
        }
      },
      {
        type   = "metric"
        x      = 0
        y      = 12
        width  = 12
        height = 6
        properties = {
          title  = "RDS CPU Utilization"
          region = var.aws_region
          metrics = [
            ["AWS/RDS", "CPUUtilization", "DBInstanceIdentifier", aws_db_instance.main.id]
          ]
          period = 300
          stat   = "Average"
        }
      },
      {
        type   = "metric"
        x      = 12
        y      = 12
        width  = 12
        height = 6
        properties = {
          title  = "RDS Database Connections"
          region = var.aws_region
          metrics = [
            ["AWS/RDS", "DatabaseConnections", "DBInstanceIdentifier", aws_db_instance.main.id]
          ]
          period = 300
          stat   = "Average"
        }
      }
    ]
  })
}


# -----------------------------------------------------------------------------
# SECTION: cloudfront
# -----------------------------------------------------------------------------

# ─── CloudFront Distribution (optional, for frontend) ─────────────────────────

resource "aws_cloudfront_distribution" "frontend" {
  count = var.enable_cloudfront ? 1 : 0

  enabled             = true
  is_ipv6_enabled     = true
  comment             = "${var.project_name} ${var.environment} frontend"
  default_root_object = "index.html"
  price_class         = "PriceClass_100"

  aliases = var.frontend_domain != "" ? [var.frontend_domain] : [var.app_domain]

  origin {
    domain_name = var.frontend_domain != "" ? var.frontend_domain : var.app_domain
    origin_id   = "frontend-origin"

    custom_origin_config {
      http_port              = 80
      https_port             = 443
      origin_protocol_policy = "https-only"
      origin_ssl_protocols   = ["TLSv1.2"]
    }
  }

  default_cache_behavior {
    allowed_methods  = ["GET", "HEAD", "OPTIONS"]
    cached_methods   = ["GET", "HEAD"]
    target_origin_id = "frontend-origin"

    forwarded_values {
      query_string = false

      cookies {
        forward = "none"
      }
    }

    viewer_protocol_policy = "redirect-to-https"
    min_ttl                = 0
    default_ttl            = 3600
    max_ttl                = 86400
    compress               = true
  }

  # Cache behavior for API requests
  ordered_cache_behavior {
    path_pattern     = "/api/*"
    allowed_methods  = ["DELETE", "GET", "HEAD", "OPTIONS", "PATCH", "POST", "PUT"]
    cached_methods   = ["GET", "HEAD"]
    target_origin_id = "api-origin"

    forwarded_values {
      query_string = true
      headers      = ["Authorization", "Origin"]

      cookies {
        forward = "all"
      }
    }

    viewer_protocol_policy = "https-only"
    min_ttl                = 0
    default_ttl            = 0
    max_ttl                = 0
  }

  origin {
    domain_name = aws_lb.main.dns_name
    origin_id   = "api-origin"

    custom_origin_config {
      http_port              = 80
      https_port             = 443
      origin_protocol_policy = "https-only"
      origin_ssl_protocols   = ["TLSv1.2"]
    }
  }

  restrictions {
    geo_restriction {
      restriction_type = "none"
    }
  }

  viewer_certificate {
    acm_certificate_arn      = aws_acm_certificate.main.arn
    ssl_support_method       = "sni-only"
    minimum_protocol_version = "TLSv1.2_2021"
  }

  tags = {
    Name = "${var.project_name}-${var.environment}-cloudfront"
  }
}


# -----------------------------------------------------------------------------
# SECTION: outputs
# -----------------------------------------------------------------------------

# ─── VPC ──────────────────────────────────────────────────────────────────────

output "vpc_id" {
  description = "ID of the VPC"
  value       = aws_vpc.main.id
}

output "public_subnet_ids" {
  description = "IDs of the public subnets"
  value       = aws_subnet.public[*].id
}

output "private_subnet_ids" {
  description = "IDs of the private subnets"
  value       = aws_subnet.private[*].id
}

# ─── ECS ──────────────────────────────────────────────────────────────────────

output "ecs_cluster_name" {
  description = "Name of the ECS cluster"
  value       = aws_ecs_cluster.main.name
}

output "ecs_cluster_arn" {
  description = "ARN of the ECS cluster"
  value       = aws_ecs_cluster.main.arn
}

output "ecs_service_name" {
  description = "Name of the backend ECS service"
  value       = aws_ecs_service.backend.name
}

output "ecs_task_definition_arn" {
  description = "ARN of the backend task definition"
  value       = aws_ecs_task_definition.backend.arn
}

# ─── RDS ──────────────────────────────────────────────────────────────────────

output "rds_endpoint" {
  description = "RDS MySQL endpoint"
  value       = aws_db_instance.main.address
}

output "rds_port" {
  description = "RDS MySQL port"
  value       = aws_db_instance.main.port
}

output "rds_database_name" {
  description = "RDS database name"
  value       = aws_db_instance.main.db_name
}

output "rds_username" {
  description = "RDS master username"
  value       = aws_db_instance.main.username
}

output "rds_password_secret_arn" {
  description = "ARN of the Secrets Manager secret containing the DB password"
  value       = aws_secretsmanager_secret.db_password.arn
}

# ─── ElastiCache ──────────────────────────────────────────────────────────────

output "redis_endpoint" {
  description = "Redis primary endpoint"
  value       = aws_elasticache_replication_group.main.primary_endpoint_address
}

output "redis_port" {
  description = "Redis port"
  value       = 6379
}

# ─── S3 ───────────────────────────────────────────────────────────────────────

output "s3_documents_bucket" {
  description = "S3 bucket for documents"
  value       = aws_s3_bucket.documents.id
}

output "s3_media_bucket" {
  description = "S3 bucket for media"
  value       = aws_s3_bucket.media.id
}

# ─── ALB ──────────────────────────────────────────────────────────────────────

output "alb_dns_name" {
  description = "DNS name of the Application Load Balancer"
  value       = aws_lb.main.dns_name
}

output "alb_zone_id" {
  description = "Zone ID of the Application Load Balancer"
  value       = aws_lb.main.zone_id
}

output "alb_arn_suffix" {
  description = "ARN suffix of the ALB (for CloudWatch metrics)"
  value       = aws_lb.main.arn_suffix
}

# ─── CloudFront ───────────────────────────────────────────────────────────────

output "cloudfront_domain_name" {
  description = "CloudFront distribution domain name"
  value       = var.enable_cloudfront ? aws_cloudfront_distribution.frontend[0].domain_name : "CloudFront not enabled"
}

# ─── Route 53 ─────────────────────────────────────────────────────────────────

output "api_fqdn" {
  description = "Fully qualified domain name for the API"
  value       = "api.${var.app_domain}"
}

output "route53_name_servers" {
  description = "Route 53 authoritative name servers to delegate for the application domain"
  value       = data.aws_route53_zone.main.name_servers
}

output "root_fqdn" {
  description = "Root domain FQDN"
  value       = var.app_domain
}

# ─── Secrets ──────────────────────────────────────────────────────────────────

output "secrets_manager_arns" {
  description = "Map of secret names to ARNs"
  value = {
    app_key               = aws_secretsmanager_secret.app_key.arn
    db_password           = aws_secretsmanager_secret.db_password.arn
    openwa                = aws_secretsmanager_secret.openwa.arn
    mail                  = aws_secretsmanager_secret.mail.arn
    aws_access_key_id     = aws_secretsmanager_secret.aws_access_key_id.arn
    aws_secret_access_key = aws_secretsmanager_secret.aws_secret_access_key.arn
  }
}

# ─── Monitoring ───────────────────────────────────────────────────────────────

output "cloudwatch_dashboard_name" {
  description = "Name of the CloudWatch dashboard"
  value       = var.enable_monitoring ? aws_cloudwatch_dashboard.main[0].dashboard_name : "Monitoring not enabled"
}

output "sns_topic_arn" {
  description = "ARN of the SNS topic for alarms"
  value       = var.enable_monitoring && var.alarm_email != "" ? aws_sns_topic.alarms[0].arn : "No SNS topic configured"
}
