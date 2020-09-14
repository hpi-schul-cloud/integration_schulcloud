<template>
	<DashboardWidget :items="items"
		:show-more-url="showMoreUrl"
		:show-more-text="title"
		:loading="state === 'loading'">
		<template v-slot:empty-content>
			<div v-if="state === 'no-token'">
				<a :href="settingsUrl">
					{{ t('integration_schulcloud', 'Click here to configure the access to your Schulcloud account.') }}
				</a>
			</div>
			<div v-else-if="state === 'error'">
				<a :href="settingsUrl">
					{{ t('integration_schulcloud', 'Incorrect API key.') }}
					{{ t('integration_schulcloud', 'Click here to configure the access to your Schulcloud account.') }}
				</a>
			</div>
			<div v-else-if="state === 'ok'">
				{{ t('integration_schulcloud', 'Nothing to show') }}
			</div>
		</template>
	</DashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import { showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import { getLocale } from '@nextcloud/l10n'

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget,
	},

	props: {
		title: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			schulcloudUrl: null,
			notifications: [],
			locale: getLocale(),
			loop: null,
			state: 'loading',
			settingsUrl: generateUrl('/settings/user/connected-accounts'),
			themingColor: OCA.Theming ? OCA.Theming.color.replace('#', '') : '0082C9',
			hovered: {},
		}
	},

	computed: {
		showMoreUrl() {
			return this.schulcloudUrl
		},
		items() {
			return this.notifications.map((n) => {
				return {
					id: this.getUniqueKey(n),
					targetUrl: this.getNotificationTarget(n),
					avatarUrl: this.getNotificationImage(n),
					avatarUsername: this.getAuthorFullName(n),
					overlayIconUrl: this.getNotificationTypeImage(n),
					mainText: this.getTargetTitle(n),
					subText: this.getSubline(n),
				}
			})
		},
		lastDate() {
			const nbNotif = this.notifications.length
			return (nbNotif > 0) ? this.notifications[0].created_at : null
		},
		lastMoment() {
			return moment(this.lastDate)
		},
	},

	beforeMount() {
		this.launchLoop()
	},

	mounted() {
	},

	methods: {
		async launchLoop() {
			// get schulcloud URL first
			try {
				const response = await axios.get(generateUrl('/apps/integration_schulcloud/url'))
				this.schulcloudUrl = response.data.replace(/\/+$/, '')
			} catch (error) {
				console.debug(error)
			}
			// then launch the loop
			this.fetchNotifications()
			this.loop = setInterval(() => this.fetchNotifications(), 60000)
		},
		fetchNotifications() {
			const req = {}
			if (this.lastDate) {
				req.params = {
					since: this.lastDate,
				}
			}
			axios.get(generateUrl('/apps/integration_schulcloud/notifications'), req).then((response) => {
				this.processNotifications(response.data)
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(t('integration_schulcloud', 'Failed to get Schulcloud notifications.'))
					this.state = 'error'
				} else {
					// there was an error in notif processing
					console.debug(error)
				}
			})
		},
		processNotifications(newNotifications) {
			if (this.lastDate) {
				// just add those which are more recent than our most recent one
				let i = 0
				while (i < newNotifications.length && this.lastMoment.isBefore(newNotifications[i].created_at)) {
					i++
				}
				if (i > 0) {
					const toAdd = this.filter(newNotifications.slice(0, i))
					this.notifications = toAdd.concat(this.notifications)
				}
			} else {
				// first time we don't check the date
				this.notifications = this.filter(newNotifications)
			}
		},
		filter(notifications) {
			return notifications.filter((n) => {
				return true
			})
		},
		getNotificationTarget(n) {
			return this.schulcloudUrl
		},
		getUniqueKey(n) {
			return n.id
		},
		getNotificationImage(n) {
			return (n.course_name)
				? generateUrl('/apps/integration_schulcloud/avatar?') + encodeURIComponent('coursename') + '=' + encodeURIComponent(n.course_name)
				: ''
		},
		getNotificationTypeImage(n) {
			if (n.notification_type === 'event') {
				return generateUrl('/svg/integration_schulcloud/calendar?color=ffffff')
			}
			return generateUrl('/svg/core/actions/sound?color=' + this.themingColor)
		},
		getSubline(n) {
			return n.course_name
		},
		getAuthorFullName(n) {
			return n.course_name
		},
		getTargetTitle(n) {
			return n.fancy_title
		},
		getFormattedDate(n) {
			return moment(n.created_at).locale(this.locale).format('LLL')
		},
	},
}
</script>

<style scoped lang="scss">
</style>
