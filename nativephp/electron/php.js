import fs from 'fs';
import fs_extra from 'fs-extra';
import { join } from 'path';
import { execSync } from 'child_process';
const { removeSync, ensureDirSync } = fs_extra;

const isBuilding = Boolean(process.env.NATIVEPHP_BUILDING);
const phpBinaryPath = process.env.NATIVEPHP_PHP_BINARY_PATH;
const phpVersion = process.env.NATIVEPHP_PHP_BINARY_VERSION;

// Differentiates for Serving and Building
const isArm64 = isBuilding ? process.argv.includes('--arm64') : process.arch.includes('arm64');
const isWindows = isBuilding ? process.argv.includes('--win') : process.platform.includes('win32');
const isLinux = isBuilding ? process.argv.includes('--linux') : process.platform.includes('linux');
const isDarwin = isBuilding ? process.argv.includes('--mac') : process.platform.includes('darwin');

// false because string mapping is done in is{OS} checks
const platform = {
    os: false,
    arch: false,
    phpBinary: 'php',
};

if (isWindows) {
    platform.os = 'win';
    platform.arch = 'x64';
    platform.phpBinary += '.exe';
}

if (isLinux) {
    platform.os = 'linux';
    platform.arch = 'x64';
}

if (isDarwin) {
    platform.os = 'mac';
    platform.arch = 'x64';
}

if (isArm64) {
    platform.arch = 'arm64';
}

// isBuilding overwrites platform to the desired architecture
if (isBuilding) {
    // Only one will be used by the configured build commands in package.json
    platform.arch = process.argv.includes('--x64') ? 'x64' : platform.arch;
    platform.arch = process.argv.includes('--arm64') ? 'arm64' : platform.arch;
}

const phpVersionZip = 'php-' + phpVersion + '.zip';
const binarySrcDir = join(phpBinaryPath, platform.os, platform.arch, phpVersionZip);
const binaryDestDir = join(process.env.NATIVEPHP_BUILD_PATH, 'php');

console.log('Binary Source: ', binarySrcDir);
console.log('Binary Filename: ', platform.phpBinary);
console.log('PHP version: ' + phpVersion);

if (platform.phpBinary) {
    try {
        console.log('Unzipping PHP binary from ' + binarySrcDir + ' to ' + binaryDestDir);
        const binaryPath = join(binaryDestDir, platform.phpBinary);

        if (fs.existsSync(binaryPath) && fs.statSync(binaryPath).size > 60000000) {
            console.log('Valid PHP binary already present at', binaryPath);
        } else {
            removeSync(binaryDestDir);
            ensureDirSync(binaryDestDir);

            if (isWindows) {
                execSync(`powershell -NoProfile -Command "Expand-Archive -Path '${binarySrcDir}' -DestinationPath '${binaryDestDir}' -Force"`, { stdio: 'inherit' });
            } else {
                execSync(`unzip -o '${binarySrcDir}' -d '${binaryDestDir}'`, { stdio: 'inherit' });
            }
            console.log('Copied PHP binary to ', binaryPath);
        }
    } catch (e) {
        console.error('Error copying PHP binary', e);
    }
}
