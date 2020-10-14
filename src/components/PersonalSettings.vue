<template>
	<div id="schulcloud_prefs" class="section">
		<h2>
			<a class="icon icon-schulcloud" />
			{{ t('integration_schulcloud', 'Schulcloud integration') }}
		</h2>
		<p v-if="!connected" class="settings-hint">
			{{ t('integration_schulcloud', 'If you fail getting access to your Schulcloud account, this is probably because your Schulcloud instance is not authorized to give API keys to your Nextcloud instance.') }}
			<br>
			{{ t('integration_schulcloud', 'Ask the Schulcloud admin to set authorized OAuth redirect URL to') }}
			<b>"web+nextcloud://sc-callback"</b>
		</p>
		<div id="schulcloud-content">
			<div class="schulcloud-grid-form">
				<label for="schulcloud-url">
					<a class="icon icon-link" />
					{{ t('integration_schulcloud', 'Schulcloud instance address') }}
				</label>
				<select id="schulcloud-url"
					v-model="state.url"
					:disabled="connected === true"
					@input="onInput">
					<option value="">
						{{ t('integration_schulcloud', 'Choose a Schul-Cloud instance') }}
					</option>
					<option value="https://test.hpi-schul-cloud.org">
						test.hpi-schul-cloud.org
					</option>
					<option value="https://oauth.test.hpi-schul-cloud.org">
						oauth.test.hpi-schul-cloud.org
					</option>
				</select>
			</div>
			<button v-if="showOAuth && !connected"
				id="schulcloud-oauth"
				@click="onOAuthClick">
				<span class="icon icon-external" />
				{{ t('integration_schulcloud', 'Connect to HPI Schul-Cloud') }}
			</button>
			<div v-if="connected" class="schulcloud-grid-form">
				<label class="schulcloud-connected">
					<a class="icon icon-checkmark-color" />
					{{ t('integration_schulcloud', 'Connected to HPI Schul-Cloud') }}
				</label>
				<button id="schulcloud-rm-cred" @click="onLogoutClick">
					<span class="icon icon-close" />
					{{ t('integration_schulcloud', 'Disconnect from HPI Schul-Cloud') }}
				</button>
				<span />
			</div>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'PersonalSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('integration_schulcloud', 'user-config'),
			readonly: true,
			// TODO choose between classic redirection (requires 'allowed user api auth redirects' => * or the specific redirect_uri)
			// and protocol handler based redirection for which 'allowed user api auth redirects' => web+nextcloud:// is enough and will work with all NC instances
			// redirect_uri: OC.getProtocol() + '://' + OC.getHostName() + generateUrl('/apps/integration_schulcloud/oauth-redirect'),
			redirect_uri: 'web+nextcloud://sc-callback',
		}
	},

	computed: {
		showOAuth() {
			return this.state.url && this.state.url !== ''
		},
		connected() {
			return this.state.url && this.state.url !== ''
				&& this.state.token && this.state.token !== ''
		},
	},

	watch: {
	},

	mounted() {
		const paramString = window.location.search.substr(1)
		// eslint-disable-next-line
		const urlParams = new URLSearchParams(paramString)
		const dscToken = urlParams.get('schulcloudToken')
		if (dscToken === 'success') {
			showSuccess(t('integration_schulcloud', 'Successfully connected to Schul-Cloud!'))
		} else if (dscToken === 'error') {
			showError(t('integration_schulcloud', 'Schul-Cloud connection error:') + ' ' + urlParams.get('message'))
		}

		// register protocol handler
		if (window.isSecureContext && window.navigator.registerProtocolHandler) {
			window.navigator.registerProtocolHandler('web+nextcloud', generateUrl('/apps/integration_schulcloud/oauth-protocol-redirect') + '?url=%s', 'Nextcloud Schulcloud integration')
		}
	},

	methods: {
		onLogoutClick() {
			this.state.token = ''
			this.saveOptions()
		},
		onInput() {
			const that = this
			delay(function() {
				that.saveOptions()
			}, 2000)()
		},
		saveOptions() {
			if (this.state.url !== '' && !this.state.url.startsWith('https://')) {
				if (this.state.url.startsWith('http://')) {
					this.state.url = this.state.url.replace('http://', 'https://')
				} else {
					this.state.url = 'https://' + this.state.url
				}
			}
			const req = {
				values: {
					token: this.state.token,
					url: this.state.url,
				},
			}
			const url = generateUrl('/apps/integration_schulcloud/config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_schulcloud', 'Schulcloud options saved'))
				})
				.catch((error) => {
					showError(
						t('integration_schulcloud', 'Failed to save Schulcloud options')
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
				})
		},
		onOAuthClick() {
			const oauthState = this.makeNonce(8)
			const myUrl = window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_schulcloud/oauth-redirect')
			const requestUrl = this.state.url + '/oauth2/auth?client_id=' + encodeURIComponent(this.state.client_id)
				// + '&redirect_uri=' + encodeURIComponent(this.redirect_uri)
				+ '&redirect_uri=' + encodeURIComponent(myUrl)
				// + '&application_name=' + encodeURIComponent('Nextcloudschulcloudintegration')
				+ '&response_type=code'
				+ '&state=' + encodeURIComponent(oauthState)
				+ '&scope=' + encodeURIComponent('offline')

			console.debug(myUrl)
			console.debug(requestUrl)
			// return

			const req = {
				values: {
					oauth_state: oauthState,
				},
			}
			const url = generateUrl('/apps/integration_schulcloud/config')
			axios.put(url, req)
				.then((response) => {
					window.location.replace(requestUrl)
				})
				.catch((error) => {
					showError(
						t('integration_schulcloud', 'Failed to save Schulcloud nonce')
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
				})
		},
		makeNonce(l) {
			let text = ''
			const chars = 'abcdefghijklmnopqrstuvwxyz0123456789'
			for (let i = 0; i < l; i++) {
				text += chars.charAt(Math.floor(Math.random() * chars.length))
			}
			return text
		},
	},
}
</script>

<style scoped lang="scss">
.schulcloud-grid-form label {
	line-height: 38px;
}

.schulcloud-grid-form input {
	width: 100%;
}

.schulcloud-grid-form {
	max-width: 600px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
	button .icon {
		margin-bottom: -1px;
	}
}

#schulcloud_prefs .icon {
	display: inline-block;
	width: 32px;
}

#schulcloud_prefs .grid-form .icon {
	margin-bottom: -3px;
}

.icon-schulcloud {
	background-image: url(./../../img/app-dark.svg);
	background-size: 23px 23px;
	height: 23px;
	margin-bottom: -4px;
}

body.theme--dark .icon-schulcloud {
	background-image: url(./../../img/app.svg);
}

#schulcloud-content {
	margin-left: 40px;
}

</style>
