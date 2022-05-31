/* global globalConfiguration */
/* global myProfileConfiguration */

import $ from 'jquery'
import './components/fullscreen_list_modal'
import './components/text_field'
import './components/tab_bar'
import { Modal } from 'bootstrap'
import { PasswordVisibilityToggle } from './components/password_visibility_toggle'
import { OwnProjectList } from './custom/own_project_list'
import Swal from 'sweetalert2'
import { deleteCookie } from './security/CookieHelper'
import MessageDialogs from './components/MessageDialogs'
import { ApiDeleteFetch, ApiPutFetch } from './api/ApiHelper'

require('../styles/custom/profile.scss')

$(() => {
  if (window.location.search.includes('profileChangeSuccess') || window.location.search.includes('profilePictureChangeSuccess')) {
    window.history.replaceState(undefined, document.title, window.location.origin + window.location.pathname)
  }

  // eslint-disable-next-line no-new
  new PasswordVisibilityToggle()

  const projectsContainer = document.getElementById('own-projects')
  const theme = projectsContainer.dataset.theme
  const baseUrl = projectsContainer.dataset.baseUrl
  const emptyMessage = projectsContainer.dataset.emptyMessage

  const url = baseUrl + '/api/projects/user'

  new OwnProjectList(projectsContainer, url, theme, emptyMessage).initialize()
  new OwnProfile().initializeAll()
})

class OwnProfile {
  initializeAll () {
    this.initProfilePictureChange()
    this.initSaveProfileSettings()
    this.initSaveSecuritySettings()
    this.initDeleteAccount()
  }

  updateProfile (data, successCallback, finalCallback) {
    new ApiPutFetch(
      '/api/user', data, 'Save Profile',
      myProfileConfiguration.messages.unspecifiedErrorText, successCallback,
      undefined, finalCallback
    ).run()
  }

  initProfilePictureChange () {
    const self = this
    Array.prototype.forEach.call(document.getElementsByClassName('profile__basic-info__avatar'), function (el) {
      el.addEventListener('click', function () {
        const input = document.createElement('input')
        input.type = 'file'
        input.accept = 'image/*'
        input.onchange = () => {
          const loadingSpinner = document.getElementById('profile-loading-spinner-template').content.cloneNode(true)
          el.appendChild(loadingSpinner)
          const reader = new window.FileReader()
          reader.onerror = () => {
            if (loadingSpinner && loadingSpinner.parentElement === el) {
              el.removeChild(loadingSpinner)
            }
            MessageDialogs.showErrorMessage(myProfileConfiguration.messages.profilePictureInvalid)
          }
          reader.onload = event => {
            const image = event.currentTarget.result // base64 data url
            self.updateProfile({ picture: image }, function () {
              window.location.search = 'profilePictureChangeSuccess'
            }, function () {
              if (loadingSpinner && loadingSpinner.parentElement === el) {
                el.removeChild(loadingSpinner)
              }
            })
          }
          reader.readAsDataURL(input.files[0])
        }
        input.click()
      })
    })
  }

  initSaveProfileSettings () {
    const self = this
    document.getElementById('profile_settings-save_action').addEventListener('click', () => {
      const form = document.getElementById('profile-settings-form')
      if (form.reportValidity() === true) {
        const formData = new window.FormData(form)
        const data = {}
        formData.forEach((value, key) => (data[key] = value))
        self.updateProfile(data, function () {
          window.location.search = 'profileChangeSuccess'
        })
      }
    })
  }

  initSaveSecuritySettings () {
    const self = this
    document.getElementById('security_settings-save_action').addEventListener('click', () => {
      const form = document.getElementById('security-settings-form')
      if (form.reportValidity() === true) {
        const formData = new window.FormData(form)
        if (formData.get('password') !== formData.get('repeat-password')) {
          MessageDialogs.showErrorMessage(myProfileConfiguration.messages.security.passwordsDontMatch)
        } else {
          self.updateProfile({
            currentPassword: formData.get('current-password'),
            password: formData.get('password')
          }, function () {
            MessageDialogs.showSuccessMessage(myProfileConfiguration.messages.passwordChangedSuccessText).then(() => {
              form.reset()
              Modal.getInstance(document.getElementById('security-settings-modal')).hide()
            })
          })
        }
      }
    })
  }

  initDeleteAccount () {
    const routingDataset = document.getElementById('js-api-routing').dataset
    document.getElementById('btn-delete-account').addEventListener('click', () => {
      const msgParts = myProfileConfiguration.userSettings.deleteAccount.confirmationText.split('\n')
      Swal.fire({
        title: msgParts[0],
        html: msgParts[1] + '<br><br>' + msgParts[2],
        icon: 'warning',
        showCancelButton: true,
        allowOutsideClick: false,
        customClass: {
          confirmButton: 'btn btn-danger',
          cancelButton: 'btn btn-outline-primary'
        },
        buttonsStyling: false,
        confirmButtonText: msgParts[3],
        cancelButtonText: msgParts[4]
      }).then((result) => {
        if (result.value) {
          new ApiDeleteFetch('/api/user', 'Delete User',
            myProfileConfiguration.messages.unspecifiedErrorText, function () {
              deleteCookie('BEARER', routingDataset.baseUrl + '/')
              window.location.href = routingDataset.index
            }).run()
        }
      })
      $('.swal2-container.swal2-shown').css('background-color', 'rgba(255, 0, 0, 0.75)')// changes the color of the overlay
    })
  }
}
