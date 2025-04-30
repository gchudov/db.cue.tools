terraform {
  required_providers {
    chef = {
      source  = "chef/chef"
      version = "~> 0.2"
    }
  }
}

provider "chef" {
  # Configuration for the Chef provider
}

resource "chef_yum_package" "tmux" {
  package_name = "tmux"
  action       = "install"
}

resource "docker_network" "ct" {
  name = "ct"
}

resource "null_resource" "install_vscode_cli" {
  provisioner "local-exec" {
    command = <<EOT
      curl -L https://code.visualstudio.com/sha/download?build=stable&os=cli-alpine-arm64 -o vscode-cli-alpine-arm64.tar.gz
      tar -xzf vscode-cli-alpine-arm64.tar.gz -C /usr/local/bin
      rm vscode-cli-alpine-arm64.tar.gz
    EOT
  }
}