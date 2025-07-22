<?php
class peerToPeer_Updater
{
    private $slug; // plugin slug
    private $pluginData; // plugin data
    private $username = 'MarkRobertson'; // GitHub username  //Pending change
    private $repo = 'peer-to-peer-crypto-wordpress-plugin'; // GitHub repo name
    private $pluginFile;
    private $githubAPIResult;

    public function __construct($pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    private function initPluginData()
    {
        $this->slug = plugin_basename($this->pluginFile);
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

        if (empty($response->slug) || $response->slug !== $this->slug) return false;

        $downloadLink = null;
        foreach ($this->githubAPIResult->assets as $asset) {
            if ($asset->name === 'peer-to-peer-crypto.zip') {
                $downloadLink = $asset->browser_download_url;
                break;
            }
        }

        if (!$downloadLink) return false;

        $response->last_updated = $this->githubAPIResult->published_at;
        $response->slug = $this->slug;
        $response->plugin_name = $this->pluginData['Name'];
        $response->version = ltrim($this->githubAPIResult->tag_name, 'v');
        $response->author = $this->pluginData['Author'];
        $response->homepage = $this->pluginData['PluginURI'];
        if (isset($this->pluginData['RequiresPHP'])) {
            $response->requires_php = $this->pluginData['RequiresPHP'];
        }
        $response->download_link = $downloadLink;

        return $response;
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