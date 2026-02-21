<?php
class peerToPeer_Updater
{
    private $slug; // plugin slug
    private $pluginData; // plugin data
    private $username = '0x4d61726'; // GitHub username  //Pending change
    private $repo = 'peer-to-peer-crypto'; // GitHub repo name
    private $pluginFile;
    private $githubAPIResult;

    public function __construct($pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    private function initPluginData()
    {
        $this->slug = 'peer-to-peer-crypto/p2pcrypto.php';
        $this->pluginData = get_plugin_data($this->pluginFile);
    }

    private function getRepoReleaseInfo()
    {
        if (!empty($this->githubAPIResult)) return;

        $url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases/latest";

        $response = wp_remote_get($url, [
            'timeout' => 60,
            'headers' => [
                'User-Agent' => 'WordPress/' . get_bloginfo('version'),
            ],
        ]);

        if (!is_wp_error($response)) {
            $this->githubAPIResult = json_decode(wp_remote_retrieve_body($response));
        }
    }

    public function setTransient($transient)
    {
        $this->initPluginData();
        $this->getRepoReleaseInfo();

        if (!isset($this->githubAPIResult->tag_name)) return $transient;

        $currentVersion = $this->pluginData['Version'];
        $latestVersion = ltrim($this->githubAPIResult->tag_name, 'v');

        if (version_compare($latestVersion, $currentVersion, '>')) {
            $package = null;

            foreach ($this->githubAPIResult->assets as $asset) {
                if ($asset->name === 'peer-to-peer-crypto.zip') {
                    $package = $asset->browser_download_url;
                    break;
                }
            }

            if ($package) {
                $obj = new stdClass();
                $obj->slug = $this->slug;
                $obj->new_version = $latestVersion;
                $obj->url = $this->pluginData["PluginURI"];
                $obj->package = $package;
                $transient->response[$this->slug] = $obj;
            }
        }

        return $transient;
    }

    public function setPluginInfo($false, $action, $response)
    {
        $this->initPluginData();
        $this->getRepoReleaseInfo();

        $acceptableSlugs = [
            $this->slug,
            dirname($this->slug),
            basename($this->slug, '.php'),
        ];

        if (empty($response->slug) || !in_array($response->slug, $acceptableSlugs)) {
            return false;
        }

        $downloadLink = null;
        foreach ($this->githubAPIResult->assets as $asset) {
            if ($asset->name === 'peer-to-peer-crypto.zip') {
                $downloadLink = $asset->browser_download_url;
                break;
            }
        }

        if (!$downloadLink) return false;

        // ✅ Required fields for modal
        $pluginInfo = new stdClass();
        $pluginInfo->name = $this->pluginData['Name'];
        $pluginInfo->slug = $response->slug;
        $pluginInfo->version = ltrim($this->githubAPIResult->tag_name, 'v');
        $pluginInfo->author = $this->pluginData['Author'];
        $pluginInfo->homepage = $this->pluginData['PluginURI'];
        $pluginInfo->requires_php = $this->pluginData['RequiresPHP'] ?? '5.6';
        $pluginInfo->requires = '5.0';
        $pluginInfo->tested = '6.5';
        $pluginInfo->last_updated = $this->githubAPIResult->published_at;
        $pluginInfo->download_link = $downloadLink;

        // ✅ Optional but useful fields
        $pluginInfo->sections = [
            'description' => $this->pluginData['Description'],
            'changelog' => '<p><strong>1.14.7</strong> - Latest release from GitHub.</p>',
        ];

        return $pluginInfo;
    }

    public function postInstall($true, $hook_extra, $result)
    {
        $this->initPluginData();
        $wasActivated = is_plugin_active($this->slug);

        if ($wasActivated) {
            activate_plugin($this->slug);
        }

        return $result;
    }
}