/* global myProfileConfiguration */

import $ from 'jquery'
import './components/fullscreen_list_modal'
import './components/text_field'
import './components/tab_bar'
import { Modal } from 'bootstrap'
import { PasswordVisibilityToggle } from './components/password_visibility_toggle'
import { OwnProjectList } from './custom/own_project_list'
import Swal from 'sweetalert2'
import { deleteCookie, getCookie } from './security/CookieHelper'

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

  new OwnProjectList(projectsContainer, url, theme, emptyMessage, showErrorMessage).initialize()
  new OwnProfile().initializeAll()
})

const showErrorMessage = function (message) {
  return Swal.fire({
    title: myProfileConfiguration.messages.errorTitle,
    text: message,
    icon: 'error',
    customClass: {
      confirmButton: 'btn btn-primary'
    },
    buttonsStyling: false,
    allowOutsideClick: false,
    confirmButtonText: myProfileConfiguration.messages.okayButtonText
  })
}

const showErrorList = function (errors) {
  if (!Array.isArray(errors)) {
    errors = Object.values(errors)
  }

  return Swal.fire({
    title: myProfileConfiguration.messages.errorTitle,
    html: '<ul class="text-start"><li>' + errors.join('</li><li>') + '</li></ul>',
    icon: 'error',
    customClass: {
      confirmButton: 'btn btn-primary'
    },
    buttonsStyling: false,
    allowOutsideClick: false,
    confirmButtonText: myProfileConfiguration.messages.okayButtonText
  })
}

const showSuccessMessage = function (message) {
  return Swal.fire({
    title: myProfileConfiguration.messages.successTitle,
    text: message,
    icon: 'success',
    customClass: {
      confirmButton: 'btn btn-primary'
    },
    buttonsStyling: false,
    allowOutsideClick: false,
    confirmButtonText: myProfileConfiguration.messages.okayButtonText
  })
}

class OwnProfile {
  initializeAll () {
    this.initProfilePictureChange()
    this.initSaveProfileSettings()
    this.initSaveSecuritySettings()
    this.initDeleteAccount()
  }

  updateProfile (data, successCallback, finalCallback) {
    window.fetch('/api/user', {
      method: 'PUT',
      headers: {
        'Content-type': 'application/json',
        Authorization: 'Bearer ' + getCookie('BEARER')
      },
      body: JSON.stringify(data)
    }).then(response => {
      switch (response.status) {
        case 204:
          // success
          successCallback()
          if (finalCallback) finalCallback()
          break
        case 401:
          // Invalid credentials
          console.error('Save Profile ERROR 401: Invalid credentials', response)
          showErrorMessage(myProfileConfiguration.messages.authenticationErrorText)
          if (finalCallback) finalCallback()
          break
        case 422:
          response.json().then(errors => {
            console.error('Save Profile ERROR 422', errors, response)
            showErrorList(errors)
            if (finalCallback) finalCallback()
          })
          break
        default:
          console.error('Save Profile ERROR', response)
          showErrorMessage(myProfileConfiguration.messages.unspecifiedErrorText)
          if (finalCallback) finalCallback()
          break
      }
    }).catch(reason => {
      console.error('Save Profile FAILURE', reason)
      showErrorMessage(myProfileConfiguration.messages.unspecifiedErrorText)
      if (finalCallback) finalCallback()
    })
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
            showErrorMessage(myProfileConfiguration.messages.profilePictureInvalid)
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
          showErrorMessage(myProfileConfiguration.messages.security.passwordsDontMatch)
        } else {
          self.updateProfile({
            currentPassword: formData.get('current-password'),
            password: formData.get('password')
          }, function () {
            showSuccessMessage(myProfileConfiguration.messages.passwordChangedSuccessText).then(() => {
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
          window.fetch('/api/user', {
            method: 'DELETE',
            headers: {
              Authorization: 'Bearer ' + getCookie('BEARER')
            }
          }).then(response => {
            switch (response.status) {
              case 204:
                deleteCookie('BEARER', routingDataset.baseUrl + '/')
                window.location.href = routingDataset.index
                break
              case 401:
                // Invalid credentials
                console.error('Delete Profile ERROR 401: Invalid credentials', response)
                showErrorMessage(myProfileConfiguration.messages.authenticationErrorText)
                break
              default:
                console.error('Delete Profile ERROR', response)
                showErrorMessage(myProfileConfiguration.messages.unspecifiedErrorText)
                break
            }
          }).catch(reason => {
            console.error('Delete Profile FAILURE', reason)
            showErrorMessage(myProfileConfiguration.messages.unspecifiedErrorText)
          })
        }
      })
      $('.swal2-container.swal2-shown').css('background-color', 'rgba(255, 0, 0, 0.75)')// changes the color of the overlay
    })
  }
}
