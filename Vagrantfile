Vagrant.configure(2) do |config|
  ##
  # 박스 이름
  ##

  config.vm.box = "ubuntu/xenial64"

  ##
  # 호스트 이름
  ##

  config.vm.hostname = "appkr"

  ##
  # SSH 설정
  ##

  config.ssh.shell = "bash -c 'BASH_ENV=/etc/profile exec bash'"
  config.ssh.forward_agent = true

  # config.vm.provision "shell" do |s|
  #   s.inline = "echo $1 | grep -xq \"$1\" /home/ubuntu/.ssh/authorized_keys || echo \"\n$1\" | tee -a /home/ubuntu/.ssh/authorized_keys"
  #   s.args = [File.read(File.expand_path(settings["authorize"]))]
  # end
  #
  # config.vm.provision "shell" do |s|
  #   s.privileged = false
  #   s.inline = "echo \"$1\" > /home/vagrant/.ssh/$2 && chmod 600 /home/vagrant/.ssh/$2"
  #   s.args = [File.read(File.expand_path(key)), key.split('/').last]
  # end

  ##
  # 가상 머신 성능 등 설정
  ##
  config.vm.provider :virtualbox do |vb|
    vb.name =  "appkr-ubuntu1604"
    vb.customize ["modifyvm", :id, "--memory", "2048"]
    vb.customize ["modifyvm", :id, "--cpus", "1"]
    vb.customize ["modifyvm", :id, "--natdnsproxy1", "on"]
    vb.customize ["modifyvm", :id, "--natdnshostresolver1", "on"]
    vb.customize ["modifyvm", :id, "--ostype", "Ubuntu_64"]
  end

  ##
  # 호스트 전용 이더넷 설정
  ##

  config.vm.network :private_network, ip: "10.0.0.21"

  ##
  # 공유 폴더
  ##

  # config.vm.synced_folder "../", "/home/ubuntu/workspace",
  #   owner: "ubuntu",
  #   group: "www-data",
  #  mount_options: ["dmode=775,fmode=664"]

  ##
  # 공유 폴더 - MySQL datadir
  ##

  # config.vm.synced_folder "host", "guest",
  #   owner: "mysql",
  #   group: "mysql",
  #   create: true

  # config.ssh.pty = true
  # config.vm.provision "shell", inline: "service apache2 restart", run: "always"
  # config.vm.provision "shell", inline: "service mysql restart", run: "always"
end
