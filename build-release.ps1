[CmdletBinding()]
param(
    [Parameter()]
    [string]$Version = '12.3',

    [Parameter()]
    [string]$PluginFolder = 'mathbinder'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression

function Ensure-Directory {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path
    )

    if (-not (Test-Path -LiteralPath $Path)) {
        New-Item -ItemType Directory -Path $Path -Force | Out-Null
    }
}

function Copy-ReleaseFile {
    param(
        [Parameter(Mandatory = $true)]
        [string]$SourcePath,

        [Parameter(Mandatory = $true)]
        [string]$DestinationPath,

        [switch]$Required
    )

    if (-not (Test-Path -LiteralPath $SourcePath -PathType Leaf)) {
        if ($Required) {
            throw "Missing required release file: $SourcePath"
        }

        return
    }

    $destinationDirectory = Split-Path -Parent $DestinationPath
    if ($destinationDirectory) {
        Ensure-Directory -Path $destinationDirectory
    }

    Copy-Item -LiteralPath $SourcePath -Destination $DestinationPath -Force
}

function Copy-ReleaseDirectory {
    param(
        [Parameter(Mandatory = $true)]
        [string]$SourceDirectory,

        [Parameter(Mandatory = $true)]
        [string]$PackageRoot
    )

    if (-not (Test-Path -LiteralPath $SourceDirectory -PathType Container)) {
        throw "Missing required release directory: $SourceDirectory"
    }

    $directoryName = Split-Path -Path $SourceDirectory -Leaf
    $destinationDirectory = Join-Path $PackageRoot $directoryName

    Copy-Item -LiteralPath $SourceDirectory -Destination $destinationDirectory -Recurse -Force
}

function Invoke-ZipValidation {
    param(
        [Parameter(Mandatory = $true)]
        [string]$ZipPath,

        [Parameter(Mandatory = $true)]
        [string]$PluginFolderName
    )

    $zip = [System.IO.Compression.ZipFile]::OpenRead($ZipPath)
    try {
        $entries = @($zip.Entries)
        $entryNames = @()

        foreach ($entry in $entries) {
            $entryNames += ($entry.FullName -replace '\\', '/')
        }

        $expectedCoreEntry = "$PluginFolderName/mathbinder-core.php"
        $expectedLogoEntry = "$PluginFolderName/assets/mathbinder-logo.svg"
        $expectedSceneEntry = "$PluginFolderName/assets/mathbinder-binder-scene-v94.png"

        $requiredDirectoryPrefixes = @(
            "$PluginFolderName/admin/",
            "$PluginFolderName/content-engine/",
            "$PluginFolderName/provisioning/"
        )

        $forbiddenFragments = @(
            '.git',
            'Backups',
            'build/',
            'Releases/',
            'Documentation/',
            'GitHub/',
            'Content Packs/'
        )

        foreach ($entryName in $entryNames) {
            if (-not $entryName.StartsWith("$PluginFolderName/", [System.StringComparison]::OrdinalIgnoreCase)) {
                throw "ZIP validation failed: entry is outside the top-level $PluginFolderName folder: $entryName"
            }

            foreach ($fragment in $forbiddenFragments) {
                if ($entryName.IndexOf($fragment, [System.StringComparison]::OrdinalIgnoreCase) -ge 0) {
                    throw "ZIP validation failed: forbidden content found in ZIP entry: $entryName"
                }
            }

            if ($entryName -match ("^{0}/MathBinder/" -f [Regex]::Escape($PluginFolderName))) {
                throw "ZIP validation failed: nested repository folder detected: $entryName"
            }
        }

        if (-not ($entryNames -contains $expectedCoreEntry)) {
            throw "ZIP validation failed: missing required entry $expectedCoreEntry"
        }

        if (-not ($entryNames -contains $expectedLogoEntry)) {
            throw "ZIP validation failed: missing required logo entry $expectedLogoEntry"
        }

        if (-not ($entryNames -contains $expectedSceneEntry)) {
            throw "ZIP validation failed: missing required scene image entry $expectedSceneEntry"
        }

        foreach ($prefix in $requiredDirectoryPrefixes) {
            $hasPrefix = $false
            foreach ($entryName in $entryNames) {
                if ($entryName.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
                    $hasPrefix = $true
                    break
                }
            }

            if (-not $hasPrefix) {
                throw "ZIP validation failed: missing required directory content under $prefix"
            }
        }
    }
    finally {
        $zip.Dispose()
    }
}

$scriptRoot = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }
$repoRoot = (Resolve-Path -LiteralPath $scriptRoot).Path

if ([string]::IsNullOrWhiteSpace($Version)) {
    $Version = '12.3'
}

$safeVersion = $Version -replace '[^0-9A-Za-z.\-]', ''
if ([string]::IsNullOrWhiteSpace($safeVersion)) {
    throw 'The -Version value is invalid.'
}

if ([string]::IsNullOrWhiteSpace($PluginFolder)) {
    throw 'The -PluginFolder value is required.'
}

if ($PluginFolder -notmatch '^[A-Za-z0-9_-]+$') {
    throw 'The -PluginFolder value is invalid. Use letters, numbers, hyphens, and underscores only.'
}

$mainPluginFile = Join-Path $repoRoot 'mathbinder-core.php'
if (-not (Test-Path -LiteralPath $mainPluginFile -PathType Leaf)) {
    throw "Missing required plugin file: $mainPluginFile"
}

$releasesRoot = Join-Path $repoRoot 'Releases'
Ensure-Directory -Path $releasesRoot

$stagingRoot = Join-Path $releasesRoot '_staging'
Ensure-Directory -Path $stagingRoot

$buildId = 'mathbinder-sprint-{0}-{1}' -f $safeVersion, ([guid]::NewGuid().ToString('N'))
$workRoot = Join-Path $stagingRoot $buildId
$packageRoot = Join-Path $workRoot $PluginFolder
$zipPath = Join-Path $releasesRoot ('mathbinder-sprint-{0}.zip' -f $safeVersion)

$includedRootFiles = New-Object System.Collections.Generic.List[string]
$includedDirectories = New-Object System.Collections.Generic.List[string]

try {
    if (Test-Path -LiteralPath $zipPath) {
        Remove-Item -LiteralPath $zipPath -Force
    }

    Ensure-Directory -Path $packageRoot

    $rootFiles = @(
        'mathbinder-core.php',
        'mathbinder-admin.css',
        'mathbinder-admin.js',
        'mathbinder-front.js',
        'mathbinder.css',
        'single-mb_binder_page.php',
        'taxonomy-mb_binder_section.php'
    )

    foreach ($rootFile in $rootFiles) {
        $sourcePath = Join-Path $repoRoot $rootFile
        $destinationPath = Join-Path $packageRoot $rootFile
        Copy-ReleaseFile -SourcePath $sourcePath -DestinationPath $destinationPath -Required
        $includedRootFiles.Add($rootFile)
    }

    $runtimeDirectories = @(
        'admin',
        'content-engine',
        'provisioning',
        'assets'
    )

    foreach ($runtimeDirectory in $runtimeDirectories) {
        $sourceDirectory = Join-Path $repoRoot $runtimeDirectory
        Copy-ReleaseDirectory -SourceDirectory $sourceDirectory -PackageRoot $packageRoot
        $includedDirectories.Add($runtimeDirectory)
    }

    $contentSource = Join-Path $repoRoot 'content'
    if (-not (Test-Path -LiteralPath $contentSource -PathType Container)) {
        throw "Missing required runtime directory: $contentSource"
    }

    Ensure-Directory -Path (Join-Path $packageRoot 'content')

    $contentFiles = @(
        'place-value.php',
        'number-operations.php',
        'number-operations-production.php'
    )

    foreach ($contentFile in $contentFiles) {
        $sourcePath = Join-Path $contentSource $contentFile
        $destinationPath = Join-Path $packageRoot (Join-Path 'content' $contentFile)
        Copy-ReleaseFile -SourcePath $sourcePath -DestinationPath $destinationPath -Required
    }
    $includedDirectories.Add('content (allowlist)')

    $fileStream = [System.IO.File]::Open($zipPath, [System.IO.FileMode]::Create)
    try {
        $zipArchive = New-Object System.IO.Compression.ZipArchive($fileStream, [System.IO.Compression.ZipArchiveMode]::Create, $false)
        try {
            $filesToArchive = Get-ChildItem -LiteralPath $packageRoot -File -Recurse
            foreach ($file in $filesToArchive) {
                $relativePath = $file.FullName.Substring($workRoot.Length).TrimStart([char[]]@('\', '/'))
                $entryName = ($relativePath -replace '\\', '/')
                $entry = $zipArchive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
                $entryStream = $entry.Open()
                $sourceStream = [System.IO.File]::OpenRead($file.FullName)
                try {
                    $sourceStream.CopyTo($entryStream)
                }
                finally {
                    $sourceStream.Dispose()
                    $entryStream.Dispose()
                }
            }
        }
        finally {
            $zipArchive.Dispose()
        }
    }
    finally {
        $fileStream.Dispose()
    }

    Invoke-ZipValidation -ZipPath $zipPath -PluginFolderName $PluginFolder

    $zipSize = (Get-Item -LiteralPath $zipPath).Length

    Write-Host 'Build complete.'
    Write-Host ('Version: {0}' -f $safeVersion)
    Write-Host ('Plugin folder: {0}' -f $PluginFolder)
    Write-Host ('ZIP path: {0}' -f $zipPath)
    Write-Host ('ZIP size: {0} bytes' -f $zipSize)
    Write-Host ('Included root files: {0}' -f ($includedRootFiles -join ', '))
    Write-Host ('Included directories: {0}' -f ($includedDirectories -join ', '))
}
catch {
    if (Test-Path -LiteralPath $zipPath) {
        Remove-Item -LiteralPath $zipPath -Force
    }

    throw
}
finally {
    if (Test-Path -LiteralPath $workRoot) {
        Remove-Item -LiteralPath $workRoot -Recurse -Force
    }
}
