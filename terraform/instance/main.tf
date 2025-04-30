# main.tf

provider "aws" {
  region     = var.aws_region
  access_key = var.aws_access_key
  secret_key = var.aws_secret_key
}

resource "aws_instance" "example" {
  ami                         = var.ami_id
  instance_type               = var.instance_type
  subnet_id                   = var.subnet_id
  vpc_security_group_ids      = var.security_group_ids
  iam_instance_profile        = var.iam_instance_profile
  key_name                    = var.key_name
  associate_public_ip_address = true
  monitoring                  = true

  root_block_device {
    volume_size = 256
    volume_type = "gp3"
  }


  tags = {
    Name = "ExampleInstance"
  }

  user_data = <<-EOF
    #!/bin/bash
    # Install required tools
    sudo yum install -y git ansible

    # Clone the repository
    git clone https://github.com/gchudov/db.cue.tools.git /opt/db.cue.tools

    cd /opt/db.cue.tools/ansible
    ansible-playbook /opt/db.cue.tools/ansible/playbook.yml
  EOF
}
