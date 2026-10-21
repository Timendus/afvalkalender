{ config, lib, pkgs, ... }:

let
  cfg = config.services.afvalkalender;
in
{
  options.services.afvalkalender = {
    enable = lib.mkEnableOption "the Afvalkalender iCalendar service";

    package = lib.mkOption {
      type = lib.types.package;
      default = pkgs.writeShellApplication {
        name = "afvalkalender";
        runtimeInputs = [ pkgs.python3 ];
        text = ''
          exec ${pkgs.python3}/bin/python ${./afvalkalender.py} "$@"
        '';
      };
      description = "The Afvalkalender application package.";
    };

    listenAddress = lib.mkOption {
      type = lib.types.str;
      default = "127.0.0.1";
      description = "Address on which the service listens.";
    };

    port = lib.mkOption {
      type = lib.types.port;
      default = 8091;
      description = "TCP port on which the service listens.";
    };
  };

  config = lib.mkIf cfg.enable {
    systemd.services.afvalkalender = {
      description = "Afvalkalender iCalendar service";
      wantedBy = [ "multi-user.target" ];
      after = [ "network-online.target" ];
      wants = [ "network-online.target" ];

      serviceConfig = {
        ExecStart = "${lib.getExe cfg.package} --host ${cfg.listenAddress} --port ${toString cfg.port} --cache-dir /var/lib/afvalkalender";
        DynamicUser = true;
        StateDirectory = "afvalkalender";
        Restart = "on-failure";
        NoNewPrivileges = true;
        PrivateTmp = true;
        PrivateDevices = true;
        ProtectSystem = "strict";
        ProtectHome = true;
        ProtectKernelTunables = true;
        ProtectKernelModules = true;
        ProtectKernelLogs = true;
        ProtectControlGroups = true;
        ProtectClock = true;
        LockPersonality = true;
        MemoryDenyWriteExecute = true;
        RestrictSUIDSGID = true;
        RestrictRealtime = true;
        RestrictNamespaces = true;
        RestrictAddressFamilies = [ "AF_UNIX" "AF_INET" "AF_INET6" ];
        CapabilityBoundingSet = "";
        AmbientCapabilities = "";
      };
    };
  };
}
