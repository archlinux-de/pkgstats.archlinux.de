package fun

// Category represents a fun statistics category with its packages.
type Category struct {
	Name     string
	Packages []string
}

// Categories lists all fun statistics categories in display order.
var Categories = []Category{
	{
		Name: "Browsers",
		Packages: []string{
			"angelfish", "brave-bin", "chromium", "dillo", "eolie", "epiphany", "elinks",
			"falkon", "firefox", "firefox-developer-edition", "google-chrome", "helium-browser-bin",
			"konqueror", "links", "lynx", "netsurf", "nyxt", "opera", "qutebrowser",
			"torbrowser-launcher", "vimb", "vivaldi", "w3m",
		},
	},
	{
		Name: "Editors",
		Packages: []string{
			"code", "e3", "ed", "emacs", "geany", "gedit", "helix", "kakoune", "kate",
			"gnome-text-editor", "gobby", "leafpad", "micro", "mousepad", "nano", "neovim",
			"notepadqq", "orbiton", "orbiton-nano", "scite", "vi", "vim", "vis", "xed", "zed",
		},
	},
	{
		Name: "Desktop Environments",
		Packages: []string{
			"budgie-desktop", "cinnamon", "cosmic-session", "cutefish-core", "deepin-session",
			"enlightenment", "gnome-session", "lxde-common", "lxqt-session",
			"mate-session-manager", "plasma-workspace", "sugar", "ukui-session-manager",
			"xfce4-session",
		},
	},
	{
		Name: "File Managers",
		Packages: []string{
			"caja", "dolphin", "konqueror", "mc", "nautilus", "nemo", "pcmanfm", "thunar",
		},
	},
	{
		Name: "Linux Kernels",
		Packages: []string{
			"linux", "linux-hardened", "linux-lts", "linux-rt", "linux-rt-lts", "linux-zen",
		},
	},
	{
		Name: "Window Managers",
		Packages: []string{
			"awesome", "blackbox", "bspwm", "cage", "ctwm", "cwm", "dwm", "fluxbox", "fvwm3",
			"herbstluftwm", "hyprland", "i3-wm", "icewm", "jwm", "labwc", "mangowc", "niri",
			"notion", "openbox", "pekwm", "qtile", "ratpoison", "river", "spectrwm", "stumpwm",
			"sway", "wayfire", "weston", "windowmaker", "xmonad", "xorg-twm",
		},
	},
	{
		Name: "Graphics Drivers",
		Packages: []string{
			"mesa", "nvidia-utils", "vulkan-broadcom", "vulkan-dzn", "vulkan-gfxstream",
			"vulkan-intel", "vulkan-nouveau", "vulkan-radeon", "vulkan-swrast",
		},
	},
	{
		Name: "Shells",
		Packages: []string{
			"bash", "dash", "dunesh", "elvish", "fish", "nushell", "oil", "tcsh", "xonsh", "zsh",
		},
	},
	{
		Name: "Terminal Emulators",
		Packages: []string{
			"alacritty", "contour", "foot", "ghostty", "gnome-console", "gnome-terminal", "kitty",
			"konsole", "lxterminal", "mate-terminal", "ptyxis", "rio", "rxvt-unicode", "terminator",
			"wezterm", "xfce4-terminal", "xterm", "yakuake",
		},
	},
	{
		Name: "Xorg GPU Drivers",
		Packages: []string{
			"nvidia-utils", "xf86-video-amdgpu", "xf86-video-ati", "xf86-video-intel",
			"xf86-video-nouveau", "xf86-video-openchrome", "xf86-video-vmware",
		},
	},
	{
		Name: "Runtime Environments",
		Packages: []string{
			"bash", "deno", "erlang-nox", "java-environment-common", "lua", "nodejs",
			"perl", "php", "python", "ruby",
		},
	},
	{
		Name: "System Languages",
		Packages: []string{
			"clang", "gcc", "ghc", "go", "nim", "ocaml", "rust", "rustup", "vala", "zig",
		},
	},
	{
		Name: "Instant Messaging Clients",
		Packages: []string{
			"caprine", "chatty", "deltachat-desktop", "dino", "discord", "element-desktop",
			"finch", "fractal", "gajim", "gomuks", "hexchat", "irssi", "jami-qt", "kaidan",
			"konversation", "kopete", "kvirc", "mcabber", "neochat", "nheko", "pidgin", //nolint:misspell
			"polari", "pork", "profanity", "psi", "senpai", "signal-desktop",
			"telegram-desktop", "tiny", "toxic", "utox", "weechat", "wire-desktop",
		},
	},
	{
		Name: "Widget Toolkits",
		Packages: []string{
			"gtk3", "gtk4", "qt5-base", "qt6-base",
		},
	},
}

// FindCategory returns the category with the given name, or nil if not found.
func FindCategory(name string) *Category {
	for i := range Categories {
		if Categories[i].Name == name {
			return &Categories[i]
		}
	}

	return nil
}
