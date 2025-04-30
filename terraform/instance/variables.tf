
# Define variables for AWS credentials
variable "aws_access_key" {
  description = "AWS access key"
  type        = string
  sensitive   = true
}
variable "aws_secret_key" {
  description = "AWS secret key"
  type        = string
  sensitive   = true
}
variable "aws_region" {
  type        = string
  description = "The AWS region to deploy resources in"
  default     = "us-east-1"
}
variable "ami_id" {
  type        = string
  description = "The AMI ID to use for the instance"
  default     = "ami-086a54924e40cab98"
}
variable "instance_type" {
  type        = string
  description = "The type of instance to create"
  default     = "m8g.large"
}
variable "subnet_id" {
  type        = string
  description = "The subnet ID to launch the instance in"
  default     = "subnet-0e728857"
}
variable "security_group_ids" {
  type        = list(string)
  description = "The security group IDs to associate with the instance"
  default     = ["sg-5d2f8a3a"]
}
variable "iam_instance_profile" {
  type        = string
  description = "The IAM instance profile to associate with the instance"
  default     = "ctdbserver"
}
variable "key_name" {
  type        = string
  description = "The key name to use for SSH access to the instance"
  default     = "ec2"
}